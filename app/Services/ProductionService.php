<?php

namespace App\Services;

use App\Models\ProductionLot;
use App\Models\ProductionLotItem;
use App\Models\ProductVariant;
use App\Models\SaleItemLot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductionService
{
    /**
     * @param  array<int|string, int|string>  $quantities
     */
    public function createLot(array $lotData, array $quantities): ProductionLot
    {
        $this->assertProducedAtIsNotFuture($lotData['produced_at'] ?? null);

        $quantities = collect($quantities)
            ->map(fn ($quantity) => (int) $quantity)
            ->filter(fn (int $quantity) => $quantity > 0);

        if ($quantities->isEmpty()) {
            throw ValidationException::withMessages([
                'variantQuantities' => 'Agrega al menos una unidad al lote.',
            ]);
        }

        return DB::transaction(function () use ($lotData, $quantities) {
            $variants = ProductVariant::query()
                ->where('active', true)
                ->whereIn('id', $quantities->keys())
                ->get()
                ->keyBy('id');

            if ($variants->count() !== $quantities->count()) {
                throw ValidationException::withMessages([
                    'variantQuantities' => 'Una de las variantes no está disponible.',
                ]);
            }

            $lot = ProductionLot::create([
                'code' => $lotData['code'] ?: $this->nextCode(),
                'produced_at' => $lotData['produced_at'],
                'expires_at' => $lotData['expires_at'] ?: null,
                'status' => 'open',
                'notes' => $lotData['notes'] ?: null,
            ]);

            foreach ($quantities as $variantId => $quantity) {
                $lot->items()->create([
                    'product_variant_id' => $variants->get((int) $variantId)->id,
                    'quantity_produced' => $quantity,
                    'quantity_available' => $quantity,
                    'unit_cost_cents' => 0,
                ]);
            }

            return $lot->load('items.productVariant.product');
        });
    }

    /**
     * Update an existing lot without altering its FIFO sales allocations.
     *
     * @param  array<int|string, int|string>  $quantities
     */
    public function updateLot(ProductionLot $productionLot, array $lotData, array $quantities): ProductionLot
    {
        $code = trim((string) ($lotData['code'] ?? ''));
        $this->assertLotDataIsValid($code, $lotData);

        return DB::transaction(function () use ($productionLot, $lotData, $quantities, $code) {
            $lot = ProductionLot::query()
                ->lockForUpdate()
                ->findOrFail($productionLot->id);

            if (ProductionLot::query()
                ->where('code', $code)
                ->whereNull('deleted_at')
                ->whereKeyNot($lot->id)
                ->lockForUpdate()
                ->exists()) {
                throw ValidationException::withMessages([
                    'editCode' => 'Ya existe un lote activo con ese código.',
                ]);
            }

            $lotItems = ProductionLotItem::query()
                ->where('production_lot_id', $lot->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('product_variant_id');
            $quantities = $this->normalizeEditQuantities($quantities, $lotItems);

            $soldQuantities = SaleItemLot::query()
                ->whereIn('production_lot_item_id', $lotItems->pluck('id'))
                ->selectRaw('production_lot_item_id, sum(quantity) as quantity_sold')
                ->groupBy('production_lot_item_id')
                ->pluck('quantity_sold', 'production_lot_item_id');
            $changedItems = [];

            foreach ($lotItems as $variantId => $lotItem) {
                $quantityProduced = $quantities[$variantId];
                $quantitySold = (int) ($soldQuantities[$lotItem->id] ?? 0);

                if ($quantityProduced < $quantitySold) {
                    throw ValidationException::withMessages([
                        "editVariantQuantities.$variantId" => 'La cantidad producida no puede ser menor que la cantidad vendida.',
                    ]);
                }

                if ($quantityProduced !== $lotItem->quantity_produced) {
                    $changedItems[$variantId] = [
                        'item' => $lotItem,
                        'quantity_produced' => $quantityProduced,
                        'quantity_sold' => $quantitySold,
                    ];
                }
            }

            if ($changedItems !== []) {
                $activeVariantCount = ProductVariant::query()
                    ->where('active', true)
                    ->whereIn('id', array_keys($changedItems))
                    ->count();

                if ($activeVariantCount !== count($changedItems)) {
                    throw ValidationException::withMessages([
                        'editVariantQuantities' => 'No se puede ajustar una variante inactiva o eliminada.',
                    ]);
                }
            }

            foreach ($changedItems as $change) {
                $change['item']->update([
                    'quantity_produced' => $change['quantity_produced'],
                    'quantity_available' => $change['quantity_produced'] - $change['quantity_sold'],
                ]);
            }

            $availableQuantity = $lotItems->sum(function (ProductionLotItem $lotItem) use ($quantities, $soldQuantities): int {
                return $quantities[$lotItem->product_variant_id] - (int) ($soldQuantities[$lotItem->id] ?? 0);
            });

            $lot->update([
                'code' => $code,
                'produced_at' => $lotData['produced_at'],
                'expires_at' => $lotData['expires_at'] ?: null,
                'notes' => $lotData['notes'] ?: null,
                'status' => $availableQuantity > 0 ? 'open' : 'depleted',
            ]);

            return $lot->fresh()->load('items.productVariant.product');
        });
    }

    /**
     * @param  array<int|string, int|string>  $quantities
     * @param  Collection<int, ProductionLotItem>  $lotItems
     * @return array<int, int>
     */
    private function normalizeEditQuantities(array $quantities, $lotItems): array
    {
        $normalized = [];

        foreach ($quantities as $variantId => $quantity) {
            if (filter_var($quantity, FILTER_VALIDATE_INT) === false || (int) $quantity < 0) {
                throw ValidationException::withMessages([
                    "editVariantQuantities.$variantId" => 'La cantidad producida debe ser un entero igual o mayor que cero.',
                ]);
            }

            $normalized[(int) $variantId] = (int) $quantity;
        }

        if (array_diff_key($normalized, $lotItems->all()) !== []
            || array_diff_key($lotItems->all(), $normalized) !== []) {
            throw ValidationException::withMessages([
                'editVariantQuantities' => 'Solo puedes editar las variantes que ya pertenecen al lote.',
            ]);
        }

        return $normalized;
    }

    private function assertLotDataIsValid(string $code, array $lotData): void
    {
        if ($code === '' || mb_strlen($code) > 60) {
            throw ValidationException::withMessages([
                'editCode' => 'El código del lote es obligatorio y no puede superar los 60 caracteres.',
            ]);
        }

        $this->assertProducedAtIsNotFuture($lotData['produced_at'] ?? null, 'editProducedAt');

        if (empty($lotData['expires_at'])) {
            return;
        }

        try {
            $expiresAt = Carbon::parse($lotData['expires_at'])->startOfDay();
            $producedAt = Carbon::parse($lotData['produced_at'])->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'editExpiresAt' => 'La fecha de vencimiento no es válida.',
            ]);
        }

        if ($expiresAt->lessThan($producedAt)) {
            throw ValidationException::withMessages([
                'editExpiresAt' => 'La fecha de vencimiento debe ser posterior a la producción.',
            ]);
        }
    }

    /**
     * Guards the data-integrity boundary: a lot produced in the future
     * must never become sellable inventory.
     */
    private function assertProducedAtIsNotFuture(mixed $producedAt, string $field = 'producedAt'): void
    {
        if ($producedAt === null || $producedAt === '') {
            throw ValidationException::withMessages([
                $field => 'La fecha de producción es obligatoria.',
            ]);
        }

        try {
            $date = Carbon::parse($producedAt);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                $field => 'La fecha de producción no es válida.',
            ]);
        }

        if ($date->startOfDay()->greaterThan(today())) {
            throw ValidationException::withMessages([
                $field => 'La fecha de producción no puede ser futura.',
            ]);
        }
    }

    private function nextCode(): string
    {
        $prefix = 'L-'.now()->format('Ymd').'-';
        $sequence = ProductionLot::query()->where('code', 'like', $prefix.'%')->count() + 1;

        do {
            $code = $prefix.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
            $sequence++;
        } while (ProductionLot::query()->where('code', $code)->exists());

        return $code;
    }
}
