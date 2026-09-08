<?php

namespace App\Services;

use App\Models\RawMaterial;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RawMaterialStockService
{
    public function receive(
        RawMaterial $material,
        float $quantity,
        int $unitCostCents,
        ?string $notes = null,
    ): RawMaterial {
        if ($quantity <= 0 || $unitCostCents < 0) {
            throw ValidationException::withMessages([
                'restockQuantity' => 'La cantidad y el costo deben ser valores válidos.',
            ]);
        }

        return DB::transaction(function () use ($material, $quantity, $unitCostCents, $notes) {
            $material = RawMaterial::query()->lockForUpdate()->findOrFail($material->id);

            $currentQuantity = (float) $material->stock_quantity;
            $newQuantity = $currentQuantity + $quantity;
            $weightedCost = $newQuantity > 0
                ? (int) round(
                    (($currentQuantity * $material->unit_cost_cents) + ($quantity * $unitCostCents))
                    / $newQuantity
                )
                : $unitCostCents;

            $material->update([
                'stock_quantity' => number_format($newQuantity, 3, '.', ''),
                'unit_cost_cents' => $weightedCost,
            ]);

            $material->movements()->create([
                'type' => 'purchase',
                'quantity' => number_format($quantity, 3, '.', ''),
                'unit_cost_cents' => $unitCostCents,
                'occurred_at' => now(),
                'notes' => $notes,
            ]);

            return $material->refresh();
        });
    }
}
