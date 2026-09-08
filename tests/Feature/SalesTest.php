<?php

namespace Tests\Feature;

use App\Livewire\Reports;
use App\Livewire\Sales;
use App\Models\Product;
use App\Models\ProductionLot;
use App\Models\ProductVariant;
use App\Models\SaleItem;
use App\Services\SalesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SalesTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_items_snapshot_the_variant_name_at_sale_time(): void
    {
        $variant = $this->variantWithStock('Maní', 5);

        $sale = app(SalesService::class)->record([$variant->id => 2], now(), null);

        $this->assertSame('Maní', $sale->items->first()->variant_name);
        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'product_variant_id' => $variant->id,
            'variant_name' => 'Maní',
        ]);
    }

    public function test_renaming_a_variant_keeps_the_historical_name_on_recent_sales(): void
    {
        $variant = $this->variantWithStock('Maní', 5);

        app(SalesService::class)->record([$variant->id => 2], now(), null);
        $variant->update(['name' => 'Maní premium']);

        // The catalog panel legitimately shows the current name, so scope the
        // assertions to the recent sales line ("<names> · <date>").
        Livewire::test(Sales::class)
            ->assertSee('Maní ·', false)
            ->assertDontSee('Maní premium ·', false);
    }

    public function test_report_keeps_historical_variant_names_and_splits_rows_after_a_rename(): void
    {
        $variant = $this->variantWithStock('Maní', 5);

        app(SalesService::class)->record([$variant->id => 2], now(), null);
        $variant->update(['name' => 'Maní premium']);
        app(SalesService::class)->record([$variant->id => 1], now(), null);

        $component = Livewire::test(Reports::class)
            ->set('dateFrom', today()->format('Y-m-d'))
            ->set('dateTo', today()->format('Y-m-d'));

        $rows = $component->viewData('rows');

        $this->assertCount(2, $rows);
        $this->assertSame(
            ['Maní', 'Maní premium'],
            $rows->pluck('variant_name')->sort()->values()->all()
        );
        $this->assertSame([4000, 2000], $rows->pluck('revenue_cents')->all());

        $component->assertSee('Maní premium')->assertSee('Maní');
    }

    public function test_report_falls_back_to_the_relation_name_when_no_snapshot_exists(): void
    {
        $variant = $this->variantWithStock('Maní', 5);
        $sale = app(SalesService::class)->record([$variant->id => 2], now(), null);

        // Simulates a row created before the snapshot column existed.
        SaleItem::query()->whereKey($sale->items->first()->id)->update(['variant_name' => '']);

        Livewire::test(Reports::class)
            ->set('dateFrom', today()->format('Y-m-d'))
            ->set('dateTo', today()->format('Y-m-d'))
            ->assertViewHas('rows', fn ($rows) => $rows->pluck('variant_name')->all() === ['Maní'])
            ->assertSee('Maní');
    }

    private function variantWithStock(string $name, int $quantity): ProductVariant
    {
        $variant = Product::create(['name' => 'Chocobanano', 'slug' => 'chocobanano'])
            ->variants()
            ->create(['name' => $name, 'sku' => 'CHO-'.strtoupper($name), 'price_cents' => 2000]);

        ProductionLot::create([
            'code' => 'LOTE-SNAP',
            'produced_at' => today(),
            'expires_at' => today()->addDays(5),
            'status' => 'open',
        ])->items()->create([
            'product_variant_id' => $variant->id,
            'quantity_produced' => $quantity,
            'quantity_available' => $quantity,
            'unit_cost_cents' => 900,
        ]);

        return $variant;
    }
}
