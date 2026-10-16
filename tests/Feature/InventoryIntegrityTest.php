<?php

namespace Tests\Feature;

use App\Livewire\Products;
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
