<?php

namespace App\Services;

use App\Models\ProductionLot;
use App\Models\ProductionLotItem;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
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
                ->with('recipeItems')
                ->where('active', true)
                ->whereIn('id', $quantities->keys())
                ->get()
                ->keyBy('id');

            if ($variants->count() !== $quantities->count()) {
                throw ValidationException::withMessages([
                    'variantQuantities' => 'Una de las variantes no está disponible.',
                ]);
            }

            $requirements = [];

            foreach ($quantities as $variantId => $quantity) {
                $variant = $variants->get((int) $variantId);

                if ($variant->recipeItems->isEmpty()) {
                    throw ValidationException::withMessages([
                        "variantQuantities.$variantId" => "La variante {$variant->name} no tiene receta configurada.",
                    ]);
                }

                foreach ($variant->recipeItems as $recipeItem) {
                    $requirements[$recipeItem->raw_material_id] =
                        ($requirements[$recipeItem->raw_material_id] ?? 0)
                        + ((float) $recipeItem->quantity_required * $quantity);
                }
            }

            $materials = RawMaterial::query()
                ->whereIn('id', array_keys($requirements))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($requirements as $materialId => $requiredQuantity) {
                $material = $materials->get($materialId);

                if (! $material || ! $material->active) {
                    throw ValidationException::withMessages([
                        'variantQuantities' => 'La receta usa una materia prima inactiva o inexistente.',
                    ]);
                }

                if ((float) $material->stock_quantity < $requiredQuantity) {
                    throw ValidationException::withMessages([
                        'variantQuantities' => "Stock insuficiente de {$material->name}. Necesitas "
                            .number_format($requiredQuantity, 3).' '.$material->unit.'.',
                    ]);
                }
            }

            $lot = ProductionLot::create([
                'code' => $lotData['code'] ?: $this->nextCode(),
                'produced_at' => $lotData['produced_at'],
                'expires_at' => $lotData['expires_at'] ?: null,
                'status' => 'open',
                'notes' => $lotData['notes'] ?: null,
            ]);

            foreach ($quantities as $variantId => $quantity) {
                $variant = $variants->get((int) $variantId);
                $unitCostCents = (int) round($variant->recipeItems->sum(
                    fn ($item) => (float) $item->quantity_required
                        * $materials->get($item->raw_material_id)->unit_cost_cents
                ));

                $lot->items()->create([
                    'product_variant_id' => $variant->id,
                    'quantity_produced' => $quantity,
                    'quantity_available' => $quantity,
                    'unit_cost_cents' => $unitCostCents,
                ]);
            }

            foreach ($requirements as $materialId => $requiredQuantity) {
                $material = $materials->get($materialId);
                $material->update([
                    'stock_quantity' => number_format(
                        (float) $material->stock_quantity - $requiredQuantity,
                        3,
                        '.',
                        ''
                    ),
                ]);

                $material->movements()->create([
                    'type' => 'production',
                    'quantity' => number_format(-$requiredQuantity, 3, '.', ''),
                    'unit_cost_cents' => $material->unit_cost_cents,
                    'reference_type' => ProductionLot::class,
                    'reference_id' => $lot->id,
                    'occurred_at' => now(),
                    'notes' => "Consumo para lote {$lot->code}",
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

                $delta = $quantityProduced - $lotItem->quantity_produced;

                if ($delta !== 0) {
                    $changedItems[$variantId] = [
                        'item' => $lotItem,
                        'quantity_produced' => $quantityProduced,
                        'quantity_sold' => $quantitySold,
                        'delta' => $delta,
                    ];
                }
            }

            $requirements = [];
            $variants = collect();

            if ($changedItems !== []) {
                $variants = ProductVariant::query()
                    ->with('recipeItems')
                    ->where('active', true)
                    ->whereIn('id', array_keys($changedItems))
                    ->get()
                    ->keyBy('id');

                if ($variants->count() !== count($changedItems)) {
                    throw ValidationException::withMessages([
                        'editVariantQuantities' => 'No se puede ajustar una variante inactiva o eliminada.',
                    ]);
                }

                foreach ($changedItems as $variantId => $change) {
                    $variant = $variants->get($variantId);

                    if ($variant->recipeItems->isEmpty()) {
                        throw ValidationException::withMessages([
                            "editVariantQuantities.$variantId" => "La variante {$variant->name} no tiene una receta activa.",
                        ]);
                    }

                    foreach ($variant->recipeItems as $recipeItem) {
                        $requirements[$recipeItem->raw_material_id] =
                            ($requirements[$recipeItem->raw_material_id] ?? 0)
                            + ((float) $recipeItem->quantity_required * $change['delta']);
                    }
                }
            }

            $materials = RawMaterial::query()
                ->whereIn('id', array_keys($requirements))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($requirements as $materialId => $requiredQuantity) {
                $material = $materials->get($materialId);

                if (! $material || ! $material->active) {
                    throw ValidationException::withMessages([
                        'editVariantQuantities' => 'La receta usa una materia prima inactiva o inexistente.',
                    ]);
                }

                if ($requiredQuantity > 0 && (float) $material->stock_quantity < $requiredQuantity) {
                    throw ValidationException::withMessages([
                        'editVariantQuantities' => "Stock insuficiente de {$material->name}. Necesitas "
                            .number_format($requiredQuantity, 3).' '.$material->unit.'.',
                    ]);
                }
            }

            foreach ($changedItems as $variantId => $change) {
                $variant = $variants->get($variantId);
                $unitCostCents = (int) round($variant->recipeItems->sum(
                    fn ($item) => (float) $item->quantity_required
                        * $materials->get($item->raw_material_id)->unit_cost_cents
                ));

                $change['item']->update([
                    'quantity_produced' => $change['quantity_produced'],
                    'quantity_available' => $change['quantity_produced'] - $change['quantity_sold'],
                    'unit_cost_cents' => $unitCostCents,
                ]);
            }

            foreach ($requirements as $materialId => $requiredQuantity) {
                if ($requiredQuantity === 0.0) {
                    continue;
                }

                $material = $materials->get($materialId);
                $material->update([
                    'stock_quantity' => number_format(
                        (float) $material->stock_quantity - $requiredQuantity,
                        3,
                        '.',
                        ''
                    ),
                ]);
                $material->movements()->create([
                    'type' => 'production_adjustment',
                    'quantity' => number_format(-$requiredQuantity, 3, '.', ''),
                    'unit_cost_cents' => $material->unit_cost_cents,
                    'reference_type' => ProductionLot::class,
                    'reference_id' => $lot->id,
                    'occurred_at' => now(),
                    'notes' => "Ajuste de producción para lote {$code}",
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
