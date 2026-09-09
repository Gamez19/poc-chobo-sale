<?php

namespace Tests\Feature;

use App\Livewire\Products;
use App\Models\Product;
use App\Models\ProductionLot;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Models\RecipeItem;
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

    private function makeMaterial(string $name, array $attributes = []): RawMaterial
    {
        return RawMaterial::create(array_merge([
            'name' => $name,
            'unit' => 'unidad',
            'stock_quantity' => 10,
            'unit_cost_cents' => 300,
            'minimum_stock' => 2,
        ], $attributes));
    }

    public function test_saving_a_recipe_creates_the_missing_recipe_items(): void
    {
        $material = $this->makeMaterial('Banano');
        $variant = $this->makeVariant($this->makeProduct(), 'Maní', 'CHO-MANI');

        $component = Livewire::test(Products::class)
            ->set("recipeQuantities.{$variant->id}.{$material->id}", '1.5000')
            ->call('saveRecipe', $variant->id)
            ->assertHasNoErrors()
            ->assertSet('statusMessage', 'Receta actualizada.');

        $item = RecipeItem::query()
            ->where('product_variant_id', $variant->id)
            ->where('raw_material_id', $material->id)
            ->first();

        $this->assertNotNull($item, 'The recipe item was not inserted.');
        $this->assertSame('1.500', $item->quantity_required);
        $this->assertNull($item->deleted_at);

        $component->assertSet("recipeQuantities.{$variant->id}.{$material->id}", '1.5');
    }

    public function test_saving_a_recipe_restores_a_soft_deleted_recipe_item_with_the_new_quantity(): void
    {
        $material = $this->makeMaterial('Banano');
        $variant = $this->makeVariant($this->makeProduct(), 'Maní', 'CHO-MANI');
        $item = $variant->recipeItems()->create([
            'raw_material_id' => $material->id,
            'quantity_required' => 1,
        ]);
        $item->delete();

        Livewire::test(Products::class)
            ->set("recipeQuantities.{$variant->id}.{$material->id}", '2.25')
            ->call('saveRecipe', $variant->id)
            ->assertHasNoErrors();

        $item->refresh();

        $this->assertNull($item->deleted_at);
        $this->assertSame('2.250', $item->quantity_required);
        $this->assertSame(1, RecipeItem::withTrashed()->count());
    }

    public function test_saving_a_recipe_updates_an_active_recipe_item_and_removes_zeroed_materials(): void
    {
        $kept = $this->makeMaterial('Banano');
        $dropped = $this->makeMaterial('Cacao');
        $variant = $this->makeVariant($this->makeProduct(), 'Maní', 'CHO-MANI');
        $keptItem = $variant->recipeItems()->create([
            'raw_material_id' => $kept->id,
            'quantity_required' => 1,
        ]);
        $droppedItem = $variant->recipeItems()->create([
            'raw_material_id' => $dropped->id,
            'quantity_required' => 3,
        ]);

        Livewire::test(Products::class)
            ->set("recipeQuantities.{$variant->id}.{$kept->id}", '4')
            ->set("recipeQuantities.{$variant->id}.{$dropped->id}", '0')
            ->call('saveRecipe', $variant->id)
            ->assertHasNoErrors();

        $this->assertSame('4.000', $keptItem->refresh()->quantity_required);
        $this->assertSoftDeleted('recipe_items', ['id' => $droppedItem->id]);
        $this->assertSame(2, RecipeItem::withTrashed()->count());
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
            ->assertDontSeeHtml('wire:click="savePrice('.$variant->id.')"')
            ->assertDontSeeHtml('wire:click="saveRecipe('.$variant->id.')"');
    }

    public function test_recipe_summary_ignores_soft_deleted_recipe_items(): void
    {
        $kept = $this->makeMaterial('Banano');
        $zeroed = $this->makeMaterial('Cacao');
        $retired = $this->makeMaterial('Colorante');
        $variant = $this->makeVariant($this->makeProduct(), 'Maní', 'CHO-MANI');
        $variant->recipeItems()->create([
            'raw_material_id' => $kept->id,
            'quantity_required' => 1,
        ]);
        $variant->recipeItems()->create([
            'raw_material_id' => $zeroed->id,
            'quantity_required' => 2,
        ])->delete();
        $variant->recipeItems()->create([
            'raw_material_id' => $retired->id,
            'quantity_required' => 2,
        ])->delete();
        $retired->delete();

        Livewire::test(Products::class)
            ->assertSee('Configurar receta · 1 insumos')
            ->assertSee('Banano')
            ->assertDontSee('Colorante');
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

    public function test_variant_without_history_is_deleted_with_its_recipe(): void
    {
        $material = RawMaterial::create([
            'name' => 'Banano',
            'unit' => 'unidad',
            'stock_quantity' => 10,
            'unit_cost_cents' => 300,
            'minimum_stock' => 2,
        ]);
        $variant = $this->makeVariant($this->makeProduct(), 'Maní', 'CHO-MANI');
        $variant->recipeItems()->create([
            'raw_material_id' => $material->id,
            'quantity_required' => 1,
        ]);

        Livewire::test(Products::class)
            ->call('removeVariant', $variant->id)
            ->assertHasNoErrors()
            ->assertSet('statusMessage', 'Variante eliminada y conservada en el historial.');

        $this->assertSoftDeleted('product_variants', ['id' => $variant->id]);
        $this->assertSoftDeleted('recipe_items', ['product_variant_id' => $variant->id]);
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

    public function test_create_variant_normalizes_name_and_sku(): void
    {
        $product = $this->makeProduct();

        Livewire::test(Products::class)
            ->set('newVariantProductId', $product->id)
            ->set('newVariantName', '  Maní  ')
            ->set('newVariantSku', '  cho-mani  ')
            ->set('newVariantPrice', '20.00')
            ->call('createVariant')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->id,
            'name' => 'Maní',
            'sku' => 'CHO-MANI',
            'price_cents' => 2000,
        ]);
    }

    public function test_create_variant_rejects_duplicate_name_within_the_same_product(): void
    {
        $product = $this->makeProduct();
        $this->makeVariant($product, 'Maní', 'CHO-MANI');

        Livewire::test(Products::class)
            ->set('newVariantProductId', $product->id)
            ->set('newVariantName', ' Maní ')
            ->set('newVariantSku', 'CHO-MANI-2')
            ->set('newVariantPrice', '20.00')
            ->call('createVariant')
            ->assertHasErrors('newVariantName');

        $this->assertSame(1, ProductVariant::where('product_id', $product->id)->count());
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
