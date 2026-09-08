<?php

namespace Tests\Feature;

use App\Livewire\Products;
use App\Livewire\RawMaterials;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function makeMaterial(string $name, array $attributes = []): RawMaterial
    {
        return RawMaterial::create(array_merge([
            'name' => $name,
            'unit' => 'unidad',
            'stock_quantity' => 0,
            'unit_cost_cents' => 0,
            'minimum_stock' => 1,
        ], $attributes));
    }

    private function makeVariant(string $name = 'Simple', string $sku = 'CHO-SIMPLE'): ProductVariant
    {
        return Product::create(['name' => 'Chocobanano '.$sku, 'slug' => 'chocobanano-'.strtolower($sku)])
            ->variants()
            ->create(['name' => $name, 'sku' => $sku, 'price_cents' => 2000]);
    }

    public function test_initial_unit_cost_is_persisted_when_initial_quantity_is_zero(): void
    {
        Livewire::test(RawMaterials::class)
            ->set('name', 'Chocolate')
            ->set('unit', 'kg')
            ->set('minimumStock', '2')
            ->set('initialQuantity', '0')
            ->set('initialUnitCost', '12.50')
            ->call('createMaterial')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('raw_materials', [
            'name' => 'Chocolate',
            'unit_cost_cents' => 1250,
        ]);

        $material = RawMaterial::where('name', 'Chocolate')->firstOrFail();
        $this->assertEquals(0, $material->stock_quantity);
        $this->assertSame(0, $material->movements()->count());
    }

    public function test_zero_quantity_initial_cost_becomes_the_restock_default(): void
    {
        Livewire::test(RawMaterials::class)
            ->set('name', 'Chocolate')
            ->set('unit', 'kg')
            ->set('minimumStock', '2')
            ->set('initialQuantity', '0')
            ->set('initialUnitCost', '12.50')
            ->call('createMaterial')
            ->assertHasNoErrors();

        $material = RawMaterial::where('name', 'Chocolate')->firstOrFail();

        Livewire::test(RawMaterials::class)
            ->call('openRestock', $material->id)
            ->assertSet('restockUnitCost', '12.50');
    }

    public function test_positive_initial_quantity_still_registers_the_weighted_average_entry(): void
    {
        Livewire::test(RawMaterials::class)
            ->set('name', 'Banano')
            ->set('unit', 'unidad')
            ->set('minimumStock', '2')
            ->set('initialQuantity', '5')
            ->set('initialUnitCost', '3.00')
            ->call('createMaterial')
            ->assertHasNoErrors();

        $material = RawMaterial::where('name', 'Banano')->firstOrFail();
        $this->assertEquals(5, $material->stock_quantity);
        $this->assertSame(300, $material->unit_cost_cents);
        $this->assertDatabaseHas('raw_material_movements', [
            'raw_material_id' => $material->id,
            'type' => 'purchase',
            'unit_cost_cents' => 300,
        ]);

        Livewire::test(RawMaterials::class)
            ->set('restockMaterialId', $material->id)
            ->set('restockQuantity', '5')
            ->set('restockUnitCost', '5.00')
            ->call('restock')
            ->assertHasNoErrors();

        $this->assertSame(400, $material->fresh()->unit_cost_cents);
    }

    public function test_inactive_material_used_by_a_recipe_is_visible_and_marked_inactive(): void
    {
        $material = $this->makeMaterial('Colorante', ['active' => false]);
        $variant = $this->makeVariant();
        $variant->recipeItems()->create([
            'raw_material_id' => $material->id,
            'quantity_required' => 2,
        ]);

        Livewire::test(Products::class)
            ->assertSee('Colorante')
            ->assertSeeHtml('wire:model="recipeQuantities.'.$variant->id.'.'.$material->id.'"')
            ->assertSeeHtml('data-testid="recipe-material-inactive-'.$variant->id.'-'.$material->id.'"');
    }

    public function test_inactive_material_without_recipe_use_is_not_offered_for_new_recipes(): void
    {
        $unused = $this->makeMaterial('Grageas', ['active' => false]);
        $active = $this->makeMaterial('Banano');
        $variant = $this->makeVariant('Maní', 'CHO-MANI');

        Livewire::test(Products::class)
            ->assertDontSee('Grageas')
            ->assertSee('Banano')
            ->assertDontSeeHtml('wire:model="recipeQuantities.'.$variant->id.'.'.$unused->id.'"')
            ->assertSeeHtml('wire:model="recipeQuantities.'.$variant->id.'.'.$active->id.'"');
    }

    public function test_inactive_material_can_be_removed_from_a_recipe(): void
    {
        $material = $this->makeMaterial('Colorante', ['active' => false]);
        $variant = $this->makeVariant();
        $variant->recipeItems()->create([
            'raw_material_id' => $material->id,
            'quantity_required' => 2,
        ]);

        Livewire::test(Products::class)
            ->assertSet("recipeQuantities.{$variant->id}.{$material->id}", '2')
            ->set("recipeQuantities.{$variant->id}.{$material->id}", '0')
            ->call('saveRecipe', $variant->id)
            ->assertHasNoErrors()
            ->assertSet('statusMessage', 'Receta actualizada.');

        $this->assertSoftDeleted('recipe_items', [
            'product_variant_id' => $variant->id,
            'raw_material_id' => $material->id,
        ]);
    }
}
