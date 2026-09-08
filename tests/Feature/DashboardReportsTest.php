<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Livewire\Reports;
use App\Models\Product;
use App\Models\ProductionLot;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Services\SalesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_finished_stock_and_variant_list_ignore_inactive_variants(): void
    {
        $active = $this->variantWithStock('Maní', 5);
        $inactive = $this->variantWithStock('Coco', 7);
        $inactive->update(['active' => false]);

        $component = Livewire::test(Dashboard::class);

        $this->assertSame(5, $component->viewData('finishedStock'));
        $this->assertSame(
            [$active->id],
            $component->viewData('variants')->pluck('id')->all(),
        );

        $component->assertSee('Maní')->assertDontSee('Coco');
    }

    public function test_low_material_alerts_ignore_inactive_materials(): void
    {
        $active = $this->lowMaterial('Leche');
        $this->lowMaterial('Cacao viejo', active: false);

        $component = Livewire::test(Dashboard::class);

        $this->assertSame(
            [$active->id],
            $component->viewData('lowMaterials')->pluck('id')->all(),
        );

        $component->assertSee('Leche')->assertDontSee('Cacao viejo');
    }

    public function test_report_with_empty_dates_shows_a_message_and_no_rows(): void
    {
        $this->recordedSale();

        $component = Livewire::test(Reports::class)
            ->set('dateFrom', '')
            ->set('dateTo', '');

        $this->assertReportIsEmpty($component);
        $component->assertSee('Ingresa un rango de fechas válido');
    }

    public function test_report_with_a_malformed_date_shows_a_message_and_no_rows(): void
    {
        $this->recordedSale();

        $component = Livewire::test(Reports::class)
            ->set('dateFrom', 'no-es-una-fecha')
            ->set('dateTo', today()->format('Y-m-d'));

        $this->assertReportIsEmpty($component);
        $component->assertSee('Ingresa un rango de fechas válido');
    }

    public function test_report_with_an_impossible_calendar_date_shows_a_message_and_no_rows(): void
    {
        $this->recordedSale();

        $component = Livewire::test(Reports::class)
            ->set('dateFrom', '2026-13-45')
            ->set('dateTo', today()->format('Y-m-d'));

        $this->assertReportIsEmpty($component);
    }

    public function test_report_rejects_an_inverted_range(): void
    {
        $this->recordedSale();

        $component = Livewire::test(Reports::class)
            ->set('dateFrom', today()->addDay()->format('Y-m-d'))
            ->set('dateTo', today()->subDay()->format('Y-m-d'));

        $this->assertReportIsEmpty($component);
        $component->assertSee('La fecha inicial no puede ser posterior a la fecha final');
    }

    public function test_report_still_returns_rows_for_a_valid_range(): void
    {
        $this->recordedSale();

        $component = Livewire::test(Reports::class)
            ->set('dateFrom', today()->format('Y-m-d'))
            ->set('dateTo', today()->format('Y-m-d'))
            ->assertHasNoErrors();

        $this->assertCount(1, $component->viewData('rows'));
        $this->assertSame(2, $component->viewData('totalUnits'));
        $this->assertSame(4000, $component->viewData('totalRevenueCents'));
        $this->assertSame(1, $component->viewData('salesCount'));
    }

    private function assertReportIsEmpty(Testable $component): void
    {
        $component->assertHasErrors('dateFrom');

        $this->assertCount(0, $component->viewData('rows'));
        $this->assertSame(0, $component->viewData('totalUnits'));
        $this->assertSame(0, $component->viewData('totalRevenueCents'));
        $this->assertSame(0, $component->viewData('salesCount'));
    }

    private function recordedSale(): void
    {
        $variant = $this->variantWithStock('Maní', 5);

        app(SalesService::class)->record([$variant->id => 2], now(), null);
    }

    private function variantWithStock(string $name, int $quantity): ProductVariant
    {
        $product = Product::query()->firstOrCreate(
            ['slug' => 'chocobanano'],
            ['name' => 'Chocobanano'],
        );

        $variant = $product->variants()->create([
            'name' => $name,
            'sku' => 'CHO-'.strtoupper($name),
            'price_cents' => 2000,
            'active' => true,
        ]);

        ProductionLot::create([
            'code' => 'LOTE-'.strtoupper($name),
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

    private function lowMaterial(string $name, bool $active = true): RawMaterial
    {
        return RawMaterial::create([
            'name' => $name,
            'unit' => 'kg',
            'stock_quantity' => 1,
            'unit_cost_cents' => 500,
            'minimum_stock' => 5,
            'active' => $active,
        ]);
    }
}
