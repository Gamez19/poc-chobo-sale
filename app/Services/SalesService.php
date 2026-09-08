<?php

namespace App\Services;

use App\Models\ProductionLot;
use App\Models\ProductionLotItem;
use App\Models\ProductVariant;
use App\Models\Sale;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesService
{
    /**
     * @param  array<int|string, int|string>  $quantities
     */
    public function record(array $quantities, CarbonInterface|string $soldAt, ?string $notes = null): Sale
    {
        $quantities = collect($quantities)
            ->map(fn ($quantity) => (int) $quantity)
            ->filter(fn (int $quantity) => $quantity > 0);

        if ($quantities->isEmpty()) {
            throw ValidationException::withMessages([
                'quantities' => 'Selecciona al menos un producto.',
            ]);
        }

        return DB::transaction(function () use ($quantities, $soldAt, $notes) {
            $variants = ProductVariant::query()
                ->with('product')
                ->where('active', true)
                ->whereIn('id', $quantities->keys())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($variants->count() !== $quantities->count()) {
                throw ValidationException::withMessages([
                    'quantities' => 'Una de las variantes no está disponible.',
                ]);
            }

            $sale = Sale::create([
                'number' => $this->nextNumber(),
                'sold_at' => $soldAt,
                'total_cents' => 0,
                'notes' => $notes ?: null,
            ]);

            $totalCents = 0;
            $affectedLotIds = [];

            foreach ($quantities as $variantId => $quantity) {
                $variant = $variants->get((int) $variantId);
                $availableLots = ProductionLotItem::query()
                    ->select('production_lot_items.*')
                    ->join('production_lots', 'production_lots.id', '=', 'production_lot_items.production_lot_id')
                    ->where('production_lot_items.product_variant_id', $variant->id)
                    ->where('production_lot_items.quantity_available', '>', 0)
                    ->where('production_lots.status', 'open')
                    ->where(fn ($query) => $query
                        ->whereNull('production_lots.expires_at')
                        ->orWhereDate('production_lots.expires_at', '>=', today()))
                    ->orderBy('production_lots.produced_at')
                    ->orderBy('production_lot_items.id')
                    ->lockForUpdate()
                    ->get();

                if ($availableLots->sum('quantity_available') < $quantity) {
                    throw ValidationException::withMessages([
                        "quantities.$variantId" => "No hay suficiente inventario de {$variant->product->name} {$variant->name}.",
                    ]);
                }

                $subtotalCents = $variant->price_cents * $quantity;
                $saleItem = $sale->items()->create([
                    'product_variant_id' => $variant->id,
                    'variant_name' => $variant->name,
                    'quantity' => $quantity,
                    'unit_price_cents' => $variant->price_cents,
                    'subtotal_cents' => $subtotalCents,
                ]);

                $remaining = $quantity;

                foreach ($availableLots as $lotItem) {
                    if ($remaining === 0) {
                        break;
                    }

                    $allocated = min($remaining, $lotItem->quantity_available);
                    $saleItem->lotAllocations()->create([
                        'production_lot_item_id' => $lotItem->id,
                        'quantity' => $allocated,
                    ]);
                    $lotItem->decrement('quantity_available', $allocated);

                    $remaining -= $allocated;
                    $affectedLotIds[] = $lotItem->production_lot_id;
                }

                $totalCents += $subtotalCents;
            }

            $sale->update(['total_cents' => $totalCents]);

            ProductionLot::query()
                ->whereIn('id', array_unique($affectedLotIds))
                ->whereDoesntHave('items', fn ($query) => $query->where('quantity_available', '>', 0))
                ->update(['status' => 'depleted']);

            return $sale->load('items.productVariant.product', 'items.lotAllocations.productionLotItem.productionLot');
        });
    }

    private function nextNumber(): string
    {
        $prefix = 'V-'.now()->format('Ymd').'-';
        $sequence = Sale::query()->where('number', 'like', $prefix.'%')->count() + 1;

        do {
            $number = $prefix.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
            $sequence++;
        } while (Sale::query()->where('number', $number)->exists());

        return $number;
    }
}
