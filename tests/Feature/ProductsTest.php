<?php

namespace Tests\Feature;

use App\Livewire\Products;
use App\Models\Product;
use App\Models\ProductionLot;
use App\Models\ProductVariant;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductsTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(string $name = 'Chocobanano', string $slug = 'chocobanano'): Product
    {
        return Product::create(['name' => $name, 'slug' => $slug]);
    }

    private function makeVariant(Product $product, string $name, string $sku): ProductVariant
    {
        return $product->variants()->create([
            'name' => $name,
            'sku' => $sku,
            'price_cents' => 2000,
        ]);
    }

    public function test_deleted_variant_hides_configuration_actions_but_keeps_restore(): void
    {
        $variant = $this->makeVariant($this->makeProduct(), 'Maní', 'CHO-MANI');

        Livewire::test(Products::class)
            ->call('removeVariant', $variant->id)
            ->assertHasNoErrors()
            ->assertSee('Maní')
            ->assertSee('Eliminada')
            ->assertSeeHtml('wire:click="restoreVariant('.$variant->id.')"')
            ->assertDontSeeHtml('wire:click="renameVariant('.$variant->id.')"')
            ->assertDontSeeHtml('wire:click="removeVariant('.$variant->id.')"')
            ->assertDontSeeHtml('wire:click="savePrice('.$variant->id.')"');
    }

    public function test_variant_can_be_renamed(): void
    {
        $variant = $this->makeVariant($this->makeProduct(), 'Maní', 'CHO-MANI');

        Livewire::test(Products::class)
            ->set("variantNames.{$variant->id}", '  Maní tostado  ')
            ->call('renameVariant', $variant->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'name' => 'Maní tostado',
        ]);
    }

    public function test_duplicate_variant_rename_is_rejected_and_leaves_database_unchanged(): void
    {
        $product = $this->makeProduct();
        $peanut = $this->makeVariant($product, 'Maní', 'CHO-MANI');
        $sprinkles = $this->makeVariant($product, 'Chispitas', 'CHO-CHIS');

        Livewire::test(Products::class)
            ->set("variantNames.{$sprinkles->id}", 'Maní')
            ->call('renameVariant', $sprinkles->id)
            ->assertHasErrors("variantNames.{$sprinkles->id}");

        $this->assertSame('Chispitas', $sprinkles->fresh()->name);
        $this->assertSame('Maní', $peanut->fresh()->name);
    }

    public function test_rename_allows_same_name_in_another_product(): void
    {
        $this->makeVariant($this->makeProduct(), 'Maní', 'CHO-MANI');
        $other = $this->makeVariant($this->makeProduct('Paleta', 'paleta'), 'Simple', 'PAL-SIMPLE');

        Livewire::test(Products::class)
            ->set("variantNames.{$other->id}", 'Maní')
            ->call('renameVariant', $other->id)
            ->assertHasNoErrors();

        $this->assertSame('Maní', $other->fresh()->name);
    }

    public function test_rename_requires_a_name_within_the_length_limit(): void
    {
        $variant = $this->makeVariant($this->makeProduct(), 'Maní', 'CHO-MANI');

        Livewire::test(Products::class)
            ->set("variantNames.{$variant->id}", '   ')
            ->call('renameVariant', $variant->id)
            ->assertHasErrors(["variantNames.{$variant->id}" => 'required'])
            ->set("variantNames.{$variant->id}", str_repeat('a', 81))
            ->call('renameVariant', $variant->id)
            ->assertHasErrors(["variantNames.{$variant->id}" => 'max']);

        $this->assertSame('Maní', $variant->fresh()->name);
    }

    public function test_variant_with_sale_history_is_soft_deleted(): void
    {
        $variant = $this->makeVariant($this->makeProduct(), 'Maní', 'CHO-MANI');
        $sale = Sale::create([
            'number' => 'V-0001',
            'sold_at' => now(),
            'total_cents' => 2000,
        ]);
        $sale->items()->create([
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'unit_price_cents' => 2000,
            'subtotal_cents' => 2000,
        ]);

        Livewire::test(Products::class)
            ->call('removeVariant', $variant->id)
            ->assertHasNoErrors()
            ->assertSet('statusMessage', 'Variante eliminada y conservada en el historial.');

        $this->assertSoftDeleted('product_variants', ['id' => $variant->id]);
        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'active' => false,
        ]);
    }

    public function test_variant_with_lot_history_is_soft_deleted(): void
    {
        $variant = $this->makeVariant($this->makeProduct(), 'Maní', 'CHO-MANI');
        ProductionLot::create([
            'code' => 'LOTE-001',
            'produced_at' => today(),
            'status' => 'open',
        ])->items()->create([
            'product_variant_id' => $variant->id,
            'quantity_produced' => 3,
            'quantity_available' => 3,
            'unit_cost_cents' => 900,
        ]);

        Livewire::test(Products::class)
            ->call('removeVariant', $variant->id)
            ->assertHasNoErrors();

        $this->assertSoftDeleted('product_variants', ['id' => $variant->id]);
        $this->assertFalse($variant->fresh()->active);
    }

    public function test_deactivated_variant_can_be_reactivated(): void
    {
        $variant = $this->makeVariant($this->makeProduct(), 'Maní', 'CHO-MANI');
        $variant->update(['active' => false]);

        Livewire::test(Products::class)
            ->call('restoreVariant', $variant->id)
            ->assertHasNoErrors()
            ->assertSet('statusMessage', 'Variante reactivada.');

        $this->assertTrue($variant->fresh()->active);
    }

    public function test_create_variant_normalizes_its_name_and_generates_the_sku_server_side(): void
    {
        $product = $this->makeProduct();

        Livewire::test(Products::class)
            ->set('newVariantProductId', $product->id)
            ->set('newVariantName', '  Maní  ')
            ->set('newVariantPrice', '20.00')
            ->call('createVariant')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->id,
            'name' => 'Maní',
            'sku' => 'CHO-CHOCOBANANO-MANI',
            'price_cents' => 2000,
        ]);
    }

    public function test_variant_form_hides_sku_controls_while_persisting_generated_skus(): void
    {
        $product = $this->makeProduct('Chocobanano Especial', 'chocobanano-especial');

        Livewire::test(Products::class)
            ->assertDontSee('SKU')
            ->set('newVariantProductId', $product->id)
            ->set('newVariantName', 'Maní')
            ->set('newVariantPrice', '20.00')
            ->call('createVariant')
            ->assertHasNoErrors()
            ->assertSet('statusMessage', 'Variante agregada correctamente.');

        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->id,
            'name' => 'Maní',
            'sku' => 'CHO-CHOCOBANANO-ESPECIAL-MANI',
        ]);
    }

    public function test_generated_skus_differ_per_category_of_the_same_product(): void
    {
        $product = $this->makeProduct('Chocobanano Especial', 'chocobanano-especial');

        Livewire::test(Products::class)
            ->set('newVariantProductId', $product->id)
            ->set('newVariantName', 'Maní')
            ->set('newVariantPrice', '20.00')
            ->call('createVariant')
            ->assertHasNoErrors()
            ->set('newVariantProductId', $product->id)
            ->set('newVariantName', 'Chispitas')
            ->set('newVariantPrice', '22.00')
            ->call('createVariant')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->id,
            'sku' => 'CHO-CHOCOBANANO-ESPECIAL-MANI',
        ]);
        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->id,
            'sku' => 'CHO-CHOCOBANANO-ESPECIAL-CHISPITAS',
        ]);
    }

    public function test_create_variant_shows_the_spanish_required_message_for_the_missing_category(): void
    {
        $product = $this->makeProduct();

        Livewire::test(Products::class)
            ->set('newVariantProductId', $product->id)
            ->set('newVariantName', '   ')
            ->set('newVariantPrice', '20.00')
            ->call('createVariant')
            ->assertHasErrors(['newVariantName' => 'required'])
            ->assertSee('El campo categoría de la variante es obligatorio.')
            ->assertDontSee('validation.required');

        $this->assertSame(0, ProductVariant::count());
    }

    public function test_create_variant_rejects_duplicate_name_within_the_same_product(): void
    {
        $product = $this->makeProduct();
        $this->makeVariant($product, 'Maní', 'CHO-MANI');

        Livewire::test(Products::class)
            ->set('newVariantProductId', $product->id)
            ->set('newVariantName', ' Maní ')
            ->set('newVariantPrice', '20.00')
            ->call('createVariant')
            ->assertHasErrors('newVariantName');

        $this->assertSame(1, ProductVariant::where('product_id', $product->id)->count());
    }

    public function test_product_card_is_collapsed_by_default_with_the_product_in_the_summary(): void
    {
        $variant = $this->makeVariant($this->makeProduct(), 'Maní', 'CHO-MANI');

        $html = Livewire::test(Products::class)
            ->assertSeeHtml('<summary class="product-card-head">')
            ->assertSeeHtml('wire:key="variant-'.$variant->id.'"')
            ->assertSeeHtml('wire:click="savePrice('.$variant->id.')"')
            ->assertSee('Chocobanano')
            ->assertSee('Sin descripción')
            ->assertSee('1 variante')
            ->html();

        $this->assertMatchesRegularExpression('/<details class="product-card"[^>]*\swire:ignore\.self[\s>]/', $html);
        $this->assertMatchesRegularExpression('/<details class="product-card"[^>]*>/', $html);
        $this->assertSame(
            1,
            preg_match('/<summary class="product-card-head">(.*?)<\/summary>/s', $html, $summary),
            'The product summary was not found.',
        );
        $this->assertSame(
            1,
            preg_match_all('/<h2 class="product-card-heading">/', $summary[1]),
            'The product summary must contain exactly one heading.',
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<(?:div|p)[\s>]/',
            $summary[1],
            'The product summary must not contain flow-content div or p elements.',
        );
        $this->assertSame(
            1,
            preg_match_all('/<h[1-6][\s>]/', $summary[1]),
            'The product summary must not contain extra heading elements.',
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<h2 class="product-card-heading">\s*<(?:div|p|h[1-6])[\s>]/',
            $summary[1],
            'The product heading must wrap phrasing content only.',
        );
        $this->assertMatchesRegularExpression(
            '/^<h2 class="product-card-heading">.*<\/h2>$/s',
            trim($summary[1]),
            'The heading must be the only element child of the product summary.',
        );
        $this->assertDoesNotMatchRegularExpression('/<details class="product-card"[^>]*\sopen[\s>]/', $html);
        $this->assertLessThan(
            strpos($html, 'wire:key="variant-'.$variant->id.'"'),
            strpos($html, '<summary class="product-card-head">'),
            'The product summary must precede its variants.',
        );
    }

    public function test_products_page_shows_inline_status_for_existing_actions(): void
    {
        $variant = $this->makeVariant($this->makeProduct(), 'Maní', 'CHO-MANI');

        Livewire::test(Products::class)
            ->set("prices.{$variant->id}", '22.50')
            ->call('savePrice', $variant->id)
            ->assertHasNoErrors()
            ->assertSet('statusMessage', 'Precio actualizado. Las ventas anteriores conservan su precio original.')
            ->assertSeeHtml('data-testid="products-status"')
            ->assertSee('Precio actualizado. Las ventas anteriores conservan su precio original.');
    }
}
