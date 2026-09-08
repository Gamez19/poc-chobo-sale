<?php

namespace Tests\Feature;

use App\Livewire\Profits;
use App\Models\Product;
use App\Models\ProductionLot;
use App\Models\Sale;
use App\Models\SaleItemLot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProfitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_profit_module_calculates_realized_and_projected_profit_by_lot_and_date(): void
    {
        $product = Product::create(['name' => 'Chocobanano', 'slug' => 'chocobanano']);
        $variant = $product->variants()->create([
            'name' => 'Maní',
            'sku' => 'CHO-MANI',
            'price_cents' => 2000,
        ]);
        $lot = ProductionLot::create([
            'code' => 'LOTE-PROFIT-001',
            'produced_at' => today(),
            'status' => 'open',
        ]);
        $lotItem = $lot->items()->create([
            'product_variant_id' => $variant->id,
            'quantity_produced' => 10,
            'quantity_available' => 6,
            'unit_cost_cents' => 900,
        ]);
        $sale = Sale::create([
            'number' => 'V-PROFIT-001',
            'sold_at' => today()->setTime(10, 0),
            'total_cents' => 8000,
        ]);
        $saleItem = $sale->items()->create([
            'product_variant_id' => $variant->id,
            'variant_name' => $variant->name,
            'quantity' => 4,
            'unit_price_cents' => 2000,
            'subtotal_cents' => 8000,
        ]);
        SaleItemLot::create([
            'sale_item_id' => $saleItem->id,
            'production_lot_item_id' => $lotItem->id,
            'quantity' => 4,
        ]);

        Livewire::test(Profits::class)
            ->set('dateFrom', today()->format('Y-m-d'))
            ->set('dateTo', today()->format('Y-m-d'))
            ->set('lotId', (string) $lot->id)
            ->assertSee('Ganancia real')
            ->assertSee('Ganancia proyectada')
            ->assertSee('C$ 44.00')
            ->assertSee('C$ 66.00')
            ->assertSee('C$ 110.00')
            ->assertSee('LOTE-PROFIT-001');
    }

    public function test_profit_module_returns_empty_results_for_invalid_dates(): void
    {
        Livewire::test(Profits::class)
            ->set('dateFrom', '2026-02-30')
            ->set('dateTo', '2026-03-01')
            ->assertHasErrors('dateFrom')
            ->assertSee('Ingresa un rango de fechas válido');
    }
}
