<?php

namespace App\Services;

use App\Models\ProductionLot;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use Illuminate\Support\Carbon;
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
     * Guards the data-integrity boundary: a lot produced in the future
     * must never become sellable inventory.
     */
    private function assertProducedAtIsNotFuture(mixed $producedAt): void
    {
        if ($producedAt === null || $producedAt === '') {
            throw ValidationException::withMessages([
                'producedAt' => 'La fecha de producción es obligatoria.',
            ]);
        }

        try {
            $date = Carbon::parse($producedAt);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'producedAt' => 'La fecha de producción no es válida.',
            ]);
        }

        if ($date->startOfDay()->greaterThan(today())) {
            throw ValidationException::withMessages([
                'producedAt' => 'La fecha de producción no puede ser futura.',
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
