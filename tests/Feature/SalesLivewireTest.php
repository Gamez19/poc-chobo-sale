<?php

namespace Tests\Feature;

use App\Livewire\Sales;
use App\Models\Product;
use App\Models\ProductionLot;
use App\Models\ProductionLotItem;
use App\Models\ProductVariant;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SalesLivewireTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirming_a_sale_shows_the_sale_number_inside_the_component(): void
    {
        $variant = $this->variantWithStock('Chocobanano', 'Maní', 5);

        $component = Livewire::test(Sales::class)
            ->set('quantities.'.$variant->id, 2)
            ->call('recordSale')
            ->assertHasNoErrors();

        $sale = Sale::query()->sole();

        $component->assertSee($sale->number)
            ->assertSee('registrada correctamente');
    }

    public function test_without_available_stock_the_component_explains_how_to_enable_sales(): void
    {
        $variant = $this->variant('Chocobanano', 'Maní');

        $html = Livewire::test(Sales::class)
            ->assertSee('receta')
            ->assertSee('lote')
            ->assertSeeHtml(route('products'))
            ->assertSeeHtml(route('production-lots'))
            ->html();

        $this->assertStringNotContainsString('id="sale-qty-'.$variant->id.'"', $html);
    }

    public function test_only_variants_with_available_stock_are_offered(): void
    {
        $sellable = $this->variantWithStock('Chocobanano', 'Maní', 3);
        $empty = $this->variant('Chocobanano', 'Coco');

        $html = Livewire::test(Sales::class)->html();

        $this->assertStringContainsString('id="sale-qty-'.$sellable->id.'"', $html);
        $this->assertStringNotContainsString('id="sale-qty-'.$empty->id.'"', $html);
    }

    public function test_search_filters_sellable_variants_by_product_and_by_variant_name(): void
    {
        $peanut = $this->variantWithStock('Chocobanano', 'Maní', 3);
        $lollipop = $this->variantWithStock('Paleta', 'Coco', 3);

        $component = Livewire::test(Sales::class)
            ->assertSeeHtml('wire:model.live.debounce.300ms="search"');

        $component->set('search', 'paleta');
        $this->assertStringContainsString('id="sale-qty-'.$lollipop->id.'"', $component->html());
        $this->assertStringNotContainsString('id="sale-qty-'.$peanut->id.'"', $component->html());

        $component->set('search', 'maní');
        $this->assertStringContainsString('id="sale-qty-'.$peanut->id.'"', $component->html());
        $this->assertStringNotContainsString('id="sale-qty-'.$lollipop->id.'"', $component->html());

        $component->set('search', 'mermelada')
            ->assertSee('No encontramos productos con ese nombre')
            ->assertDontSee('Para vender necesitas configurar la receta');
    }

    public function test_total_uses_live_quantities_and_survives_a_search_that_hides_the_selection(): void
    {
        $peanut = $this->variantWithStock('Chocobanano', 'Maní', 3);
        $this->variantWithStock('Paleta', 'Coco', 3);

        $component = Livewire::test(Sales::class)
            ->assertSeeHtml('wire:model.live.debounce.400ms="quantities.'.$peanut->id.'"')
            ->set('quantities.'.$peanut->id, 2)
            ->assertSee('C$ 40.00');

        $component->set('search', 'paleta')
            ->assertSee('C$ 40.00');

        $this->assertSame(2, (int) $component->get('quantities')[$peanut->id]);
    }

    public function test_quantities_only_track_active_variants(): void
    {
        $active = $this->variantWithStock('Chocobanano', 'Maní', 3);
        $inactive = $this->variant('Chocobanano', 'Coco', active: false);

        $component = Livewire::test(Sales::class);

        $this->assertSame([$active->id], $this->quantityKeys($component->get('quantities')));

        $component->set('quantities.'.$active->id, 1)
            ->call('recordSale')
            ->assertHasNoErrors();

        $this->assertSame([$active->id], $this->quantityKeys($component->get('quantities')));
        $this->assertSame([0], array_values($component->get('quantities')));
        $this->assertNotContains($inactive->id, $this->quantityKeys($component->get('quantities')));
    }

    public function test_variants_are_ordered_by_product_then_variant(): void
    {
        $second = $this->variantWithStock('Brownie', 'Ana', 2);
        $first = $this->variantWithStock('Alfajor', 'Zeta', 2);

        $variants = Livewire::test(Sales::class)->viewData('variants');

        $this->assertSame(
            [$first->id, $second->id],
            $variants->pluck('id')->all(),
        );
    }

    public function test_insufficient_stock_does_not_create_a_sale_or_move_inventory(): void
    {
        $variant = $this->variantWithStock('Chocobanano', 'Maní', 2);

        Livewire::test(Sales::class)
            ->set('quantities.'.$variant->id, 5)
            ->call('recordSale')
            ->assertHasErrors('quantities.'.$variant->id)
            ->assertDontSee('registrada correctamente');

        $this->assertSame(0, Sale::query()->count());
        $this->assertSame(2, (int) ProductionLotItem::query()->sole()->quantity_available);
    }

    /**
     * @param  array<int|string, int|string>  $quantities
     * @return array<int, int>
     */
    private function quantityKeys(array $quantities): array
    {
        return array_map('intval', array_keys($quantities));
    }

    private function variant(string $productName, string $variantName, bool $active = true): ProductVariant
    {
        $product = Product::query()->firstOrCreate(
            ['slug' => str($productName)->slug()->value()],
            ['name' => $productName],
        );

        return $product->variants()->create([
            'name' => $variantName,
            'sku' => strtoupper(str($productName.'-'.$variantName)->slug()->value()),
            'price_cents' => 2000,
            'active' => $active,
        ]);
    }

    private function variantWithStock(string $productName, string $variantName, int $quantity): ProductVariant
    {
        $variant = $this->variant($productName, $variantName);

        ProductionLot::create([
            'code' => 'LOTE-'.strtoupper($variantName),
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
