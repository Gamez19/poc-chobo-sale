<?php

namespace Tests\Feature;

use App\Livewire\Products;
use App\Livewire\Reports;
use App\Models\Product;
use App\Models\ProductionLot;
use App\Models\RawMaterial;
use App\Services\ProductionService;
use App\Services\SalesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_consumes_recipe_materials_and_creates_stock(): void
    {
        $material = RawMaterial::create([
            'name' => 'Banano',
            'unit' => 'unidad',
            'stock_quantity' => 10,
            'unit_cost_cents' => 300,
            'minimum_stock' => 2,
        ]);
        $variant = Product::create(['name' => 'Chocobanano', 'slug' => 'chocobanano'])
            ->variants()
            ->create(['name' => 'Simple', 'sku' => 'CHO-SIMPLE', 'price_cents' => 1500]);
        $variant->recipeItems()->create([
            'raw_material_id' => $material->id,
            'quantity_required' => 1,
        ]);

        $lot = app(ProductionService::class)->createLot([
            'code' => 'LOTE-TEST-001',
            'produced_at' => today(),
            'expires_at' => today()->addWeek(),
            'notes' => null,
        ], [$variant->id => 3]);

        $this->assertSame(3, $lot->items->first()->quantity_available);
        $this->assertSame(300, $lot->items->first()->unit_cost_cents);
        $this->assertEquals(7, RawMaterial::find($material->id)->stock_quantity);
        $this->assertDatabaseHas('raw_material_movements', [
            'raw_material_id' => $material->id,
            'type' => 'production',
            'quantity' => -3,
            'reference_id' => $lot->id,
        ]);
    }

    public function test_sales_allocate_oldest_lots_and_preserve_the_price(): void
    {
        $variant = Product::create(['name' => 'Chocobanano', 'slug' => 'chocobanano'])
            ->variants()
            ->create(['name' => 'Maní', 'sku' => 'CHO-MANI', 'price_cents' => 2000]);

        $oldLot = ProductionLot::create([
            'code' => 'LOTE-OLD',
            'produced_at' => today()->subDay(),
            'expires_at' => today()->addDays(5),
            'status' => 'open',
        ]);
        $newLot = ProductionLot::create([
            'code' => 'LOTE-NEW',
            'produced_at' => today(),
            'expires_at' => today()->addDays(6),
            'status' => 'open',
        ]);
        $oldItem = $oldLot->items()->create([
            'product_variant_id' => $variant->id,
            'quantity_produced' => 2,
            'quantity_available' => 2,
            'unit_cost_cents' => 900,
        ]);
        $newItem = $newLot->items()->create([
            'product_variant_id' => $variant->id,
            'quantity_produced' => 3,
            'quantity_available' => 3,
            'unit_cost_cents' => 900,
        ]);

        $sale = app(SalesService::class)->record([$variant->id => 4], now(), null);
        $variant->update(['price_cents' => 2500]);

        $this->assertSame(8000, $sale->total_cents);
        $this->assertSame(2000, $sale->items->first()->unit_price_cents);
        $this->assertSame(0, $oldItem->fresh()->quantity_available);
        $this->assertSame(1, $newItem->fresh()->quantity_available);
        $this->assertSame(2, $sale->items->first()->lotAllocations()->count());
    }

    public function test_all_operational_pages_render_with_seed_data(): void
    {
        $this->seed();

        foreach (['/', '/materias-primas', '/productos', '/lotes', '/ventas', '/reportes'] as $uri) {
            $this->get($uri)->assertOk();
        }

        $this->get('/productos')
            ->assertSee('Simple')
            ->assertSee('Maní')
            ->assertSee('Chispitas');
    }

    public function test_each_variant_price_can_be_updated_independently(): void
    {
        $this->seed();
        $variant = Product::where('slug', 'chocobanano')
            ->firstOrFail()
            ->variants()
            ->where('name', 'Maní')
            ->firstOrFail();

        Livewire::test(Products::class)
            ->set("prices.{$variant->id}", '22.50')
            ->call('savePrice', $variant->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'price_cents' => 2250,
        ]);
    }

    public function test_report_filters_sales_by_date_and_lot(): void
    {
        $this->seed();
        $lot = ProductionLot::where('code', 'LOTE-CHOCO-001')->firstOrFail();

        Livewire::test(Reports::class)
            ->set('dateFrom', today()->subDays(2)->format('Y-m-d'))
            ->set('dateTo', today()->format('Y-m-d'))
            ->set('lotId', (string) $lot->id)
            ->assertViewHas('totalUnits', 10)
            ->assertViewHas('totalRevenueCents', 17500)
            ->set('dateFrom', today()->addDay()->format('Y-m-d'))
            ->set('dateTo', today()->addDays(2)->format('Y-m-d'))
            ->assertViewHas('totalUnits', 0);
    }
}
