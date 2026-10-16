<?php

namespace Tests\Feature;

use App\Livewire\Profits;
use App\Models\Product;
use App\Models\ProductionLot;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\RawMaterial;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProfitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_profit_is_sales_minus_purchases_of_the_period(): void
    {
        $variant = $this->variant('Maní');
        $this->sale($variant, 4, today()->setTime(10, 0));
        $this->purchase(15000, today(), ['Bananos']);
        $this->purchase(18000, today(), ['Chocolate']);

        Livewire::test(Profits::class)
            ->set('dateFrom', today()->format('Y-m-d'))
            ->set('dateTo', today()->format('Y-m-d'))
            ->assertSee('Ventas')
            ->assertSee('C$ 80.00')
            ->assertSee('Compras')
            ->assertSee('C$ 330.00')
            ->assertSee('Ganancia real')
            ->assertSee('C$ -250.00')
            ->assertSee('Margen real')
            ->assertSee('-312.5 %')
            ->assertDontSee('Ganancia proyectada');
    }

    public function test_positive_profit_and_margin_are_calculated(): void
    {
        $variant = $this->variant('Maní');
        $this->sale($variant, 4, today()->setTime(10, 0));
        $this->purchase(3600, today(), ['Bananos']);

        Livewire::test(Profits::class)
            ->set('dateFrom', today()->format('Y-m-d'))
            ->set('dateTo', today()->format('Y-m-d'))
            ->assertSee('C$ 44.00')
            ->assertSee('55.0 %');
    }

    public function test_purchases_and_sales_outside_the_range_are_excluded(): void
    {
        $variant = $this->variant('Maní');
        $this->sale($variant, 4, today()->setTime(10, 0));
        $this->sale($variant, 10, today()->subMonths(2)->setTime(10, 0));
        $this->purchase(5000, today(), ['Bananos']);
        $this->purchase(99000, today()->subMonths(2), ['Fuera de rango']);

        $component = Livewire::test(Profits::class)
            ->set('dateFrom', today()->format('Y-m-d'))
            ->set('dateTo', today()->format('Y-m-d'));

        $this->assertSame(8000, $component->viewData('salesCents'));
        $this->assertSame(5000, $component->viewData('purchasesCents'));
        $this->assertSame(3000, $component->viewData('profitCents'));
        $component->assertSee('Bananos')->assertDontSee('Fuera de rango');
    }

    public function test_detail_lists_sales_by_historical_variant_name_and_purchases(): void
    {
        $variant = $this->variant('Maní');
        $this->sale($variant, 3, today()->setTime(9, 0));
        $variant->update(['name' => 'Maní renombrado']);
        $this->purchase(15000, today(), ['Bananos', 'Chocolate'], 'Mercado central');

        Livewire::test(Profits::class)
            ->set('dateFrom', today()->format('Y-m-d'))
            ->set('dateTo', today()->format('Y-m-d'))
            ->assertSee('Maní')
            ->assertDontSee('Maní renombrado')
            ->assertSee('Bananos, Chocolate')
            ->assertSee('Mercado central')
            ->assertSee('C$ 150.00');
    }

    public function test_potential_income_shows_available_stock_at_current_price_without_costs(): void
    {
        $this->variantWithStock('Coco', 6);

        Livewire::test(Profits::class)
            ->assertSee('Ingreso potencial')
            ->assertSee('C$ 120.00')
            ->assertDontSee('Lote');
    }

    public function test_margin_is_zero_when_there_are_no_sales(): void
    {
        $this->purchase(5000, today(), ['Bananos']);

        Livewire::test(Profits::class)
            ->set('dateFrom', today()->format('Y-m-d'))
            ->set('dateTo', today()->format('Y-m-d'))
            ->assertSee('Margen real')
            ->assertSee('0.0 %')
            ->assertSee('C$ -50.00');
    }

    public function test_profit_module_returns_empty_results_for_invalid_dates(): void
    {
        $this->purchase(5000, today(), ['Bananos']);

        Livewire::test(Profits::class)
            ->set('dateFrom', '2026-02-30')
            ->set('dateTo', '2026-03-01')
            ->assertHasErrors('dateFrom')
            ->assertSee('Ingresa un rango de fechas válido');
    }

    public function test_profit_module_rejects_an_inverted_range(): void
    {
        Livewire::test(Profits::class)
            ->set('dateFrom', today()->addDay()->format('Y-m-d'))
            ->set('dateTo', today()->subDay()->format('Y-m-d'))
            ->assertHasErrors('dateFrom')
            ->assertSee('La fecha inicial no puede ser posterior a la fecha final');
    }

    private function variant(string $name): ProductVariant
    {
        $product = Product::query()->firstOrCreate(
            ['slug' => 'chocobanano'],
            ['name' => 'Chocobanano'],
        );

        return $product->variants()->create([
            'name' => $name,
            'sku' => 'CHO-'.strtoupper($name),
            'price_cents' => 2000,
        ]);
    }

    private function variantWithStock(string $name, int $quantity): ProductVariant
    {
        $variant = $this->variant($name);

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

    private function sale(ProductVariant $variant, int $quantity, \DateTimeInterface $soldAt): Sale
    {
        $sale = Sale::create([
            'number' => 'V-'.fake()->unique()->numerify('######'),
            'sold_at' => $soldAt,
            'total_cents' => $quantity * 2000,
        ]);
        $sale->items()->create([
            'product_variant_id' => $variant->id,
            'variant_name' => $variant->name,
            'quantity' => $quantity,
            'unit_price_cents' => 2000,
            'subtotal_cents' => $quantity * 2000,
        ]);

        return $sale;
    }

    /**
     * @param  list<string>  $materialNames
     */
    private function purchase(int $totalCents, \DateTimeInterface $purchasedAt, array $materialNames, ?string $notes = null): Purchase
    {
        $purchase = Purchase::create([
            'purchased_at' => $purchasedAt,
            'total_cents' => $totalCents,
            'notes' => $notes,
        ]);
        $purchase->rawMaterials()->attach(
            collect($materialNames)
                ->map(fn (string $name): int => RawMaterial::query()->firstOrCreate(
                    ['name' => $name],
                    ['unit' => 'unidad'],
                )->id)
                ->all(),
        );

        return $purchase;
    }
}
