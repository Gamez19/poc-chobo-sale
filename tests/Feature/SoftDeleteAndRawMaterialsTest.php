<?php

namespace Tests\Feature;

use App\Livewire\Products;
use App\Livewire\RawMaterials;
use App\Models\Product;
use App\Models\ProductionLot;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SoftDeleteAndRawMaterialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_variant_deletion_is_soft_and_restore_recovers_its_recipe(): void
    {
        $material = $this->makeMaterial('Cacao');
        $variant = $this->makeVariant();
        $variant->recipeItems()->create([
            'raw_material_id' => $material->id,
            'quantity_required' => 2,
        ]);

        Livewire::test(Products::class)
            ->call('removeVariant', $variant->id)
            ->assertHasNoErrors()
            ->assertSet('statusMessage', 'Variante eliminada y conservada en el historial.');

        $this->assertSoftDeleted('product_variants', ['id' => $variant->id]);
        $this->assertSoftDeleted('recipe_items', [
            'product_variant_id' => $variant->id,
            'raw_material_id' => $material->id,
        ]);

        Livewire::test(Products::class)
            ->call('restoreVariant', $variant->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'active' => true,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('recipe_items', [
            'product_variant_id' => $variant->id,
            'raw_material_id' => $material->id,
            'deleted_at' => null,
        ]);
    }

    public function test_deleted_variant_does_not_block_reusing_its_name_and_sku(): void
    {
        $product = $this->makeProduct();
        $variant = $this->makeVariant($product);
        $variant->delete();

        Livewire::test(Products::class)
            ->set('newVariantProductId', $product->id)
            ->set('newVariantName', $variant->name)
            ->set('newVariantSku', $variant->sku)
            ->set('newVariantPrice', '25.00')
            ->call('createVariant')
            ->assertHasNoErrors();

        $this->assertSame(
            1,
            ProductVariant::query()
                ->where('product_id', $product->id)
                ->where('name', $variant->name)
                ->count(),
        );
    }

    public function test_soft_deleted_lot_remains_available_to_inventory_history_relations(): void
    {
        $variant = $this->makeVariant();
        $lot = ProductionLot::create([
            'code' => 'LOTE-HISTORY-001',
            'produced_at' => today(),
            'status' => 'open',
        ]);
        $lotItem = $lot->items()->create([
            'product_variant_id' => $variant->id,
            'quantity_produced' => 3,
            'quantity_available' => 2,
            'unit_cost_cents' => 900,
        ]);
        $lot->delete();

        $lotItem->load('productionLot');

        $this->assertSoftDeleted('production_lots', ['id' => $lot->id]);
        $this->assertSame($lot->id, $lotItem->productionLot->id);
    }

    public function test_soft_deleted_lot_is_not_available_for_new_sales(): void
    {
        $variant = $this->makeVariant();
        $lot = ProductionLot::create([
            'code' => 'LOTE-UNAVAILABLE-001',
            'produced_at' => today(),
            'status' => 'open',
        ]);
        $lot->items()->create([
            'product_variant_id' => $variant->id,
            'quantity_produced' => 3,
            'quantity_available' => 3,
            'unit_cost_cents' => 900,
        ]);
        $lot->delete();

        $this->assertSame(0, $variant->availableLotItems()->count());
    }

    public function test_soft_deleted_variant_remains_available_to_sale_history_relations(): void
    {
        $variant = $this->makeVariant();
        $sale = Sale::create([
            'number' => 'V-HISTORY-001',
            'sold_at' => now(),
            'total_cents' => 2000,
        ]);
        $saleItem = $sale->items()->create([
            'product_variant_id' => $variant->id,
            'variant_name' => $variant->name,
            'quantity' => 1,
            'unit_price_cents' => 2000,
            'subtotal_cents' => 2000,
        ]);
        $variant->delete();

        $saleItem->load('productVariant.product');

        $this->assertSame($variant->id, $saleItem->productVariant->id);
        $this->assertSame($variant->product->id, $saleItem->productVariant->product->id);
    }

    public function test_raw_material_can_be_created_with_libra_as_an_independent_unit(): void
    {
        Livewire::test(RawMaterials::class)
            ->set('name', 'Azúcar')
            ->set('unit', 'libra')
            ->set('minimumStock', '2')
            ->set('initialQuantity', '3')
            ->set('initialUnitCost', '4.50')
            ->call('createMaterial')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('raw_materials', [
            'name' => 'Azúcar',
            'unit' => 'libra',
        ]);
    }

    public function test_raw_material_can_be_created_with_onzas_as_a_supported_unit(): void
    {
        Livewire::test(RawMaterials::class)
            ->set('name', 'Chocolate')
            ->set('unit', 'onzas')
            ->set('minimumStock', '2.50')
            ->set('initialQuantity', '3.25')
            ->set('initialUnitCost', '4.50')
            ->call('createMaterial')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('raw_materials', [
            'name' => 'Chocolate',
            'unit' => 'onzas',
        ]);
    }

    public function test_raw_material_page_shows_quantities_with_two_decimals(): void
    {
        $this->makeMaterial('Azúcar', ['stock_quantity' => '2.250', 'minimum_stock' => 1]);

        Livewire::test(RawMaterials::class)
            ->assertSee('2.25 unidad')
            ->assertSee('Mínimo: 1.00 unidad')
            ->call('openRestock', RawMaterial::query()->where('name', 'Azúcar')->value('id'))
            ->assertSee('2.25 unidad actuales');
    }

    public function test_raw_material_editing_updates_configuration_without_changing_stock_or_cost(): void
    {
        $material = $this->makeMaterial('Azucar', [
            'stock_quantity' => '4.500',
            'unit_cost_cents' => 750,
            'minimum_stock' => 1,
        ]);

        Livewire::test(RawMaterials::class)
            ->call('openEdit', $material->id)
            ->assertSet('editName', 'Azucar')
            ->assertSet('editUnit', 'unidad')
            ->assertSet('editMinimumStock', '1.00')
            ->set('editName', '  Azúcar refinada  ')
            ->set('editUnit', 'onzas')
            ->set('editMinimumStock', '2.25')
            ->call('updateMaterial')
            ->assertHasNoErrors()
            ->assertSet('editMaterialId', null);

        $material->refresh();

        $this->assertSame('Azúcar refinada', $material->name);
        $this->assertSame('onzas', $material->unit);
        $this->assertSame('2.250', $material->minimum_stock);
        $this->assertSame('4.500', $material->stock_quantity);
        $this->assertSame(750, $material->unit_cost_cents);
    }

    public function test_raw_material_editing_rejects_a_duplicate_active_name(): void
    {
        $this->makeMaterial('Cacao');
        $material = $this->makeMaterial('Leche');

        Livewire::test(RawMaterials::class)
            ->call('openEdit', $material->id)
            ->set('editName', 'Cacao')
            ->call('updateMaterial')
            ->assertHasErrors(['editName' => 'unique'])
            ->set('editName', 'Leche')
            ->call('updateMaterial')
            ->assertHasNoErrors();

        $this->assertSame('Leche', $material->refresh()->name);
    }

    public function test_raw_material_editing_validates_unit_and_minimum_stock(): void
    {
        $material = $this->makeMaterial('Harina');

        Livewire::test(RawMaterials::class)
            ->call('openEdit', $material->id)
            ->set('editUnit', 'toneladas')
            ->call('updateMaterial')
            ->assertHasErrors(['editUnit' => 'in'])
            ->set('editUnit', 'gramos')
            ->set('editMinimumStock', '-1')
            ->call('updateMaterial')
            ->assertHasErrors(['editMinimumStock' => 'min'])
            ->set('editMinimumStock', '1.234')
            ->call('updateMaterial')
            ->assertHasErrors(['editMinimumStock' => 'decimal']);

        $material->refresh();

        $this->assertSame('unidad', $material->unit);
        $this->assertSame('1.000', $material->minimum_stock);
    }

    public function test_new_material_form_is_rendered_before_the_inventory_list(): void
    {
        $this->makeMaterial('Azúcar');

        $html = Livewire::test(RawMaterials::class)->html();

        $this->assertLessThan(
            strpos($html, 'Inventario de insumos'),
            strpos($html, 'Nueva materia prima'),
            'The new material form must come before the inventory list in document order.',
        );
    }

    public function test_material_row_opens_the_detail_and_its_actions_replace_it_without_stacking(): void
    {
        $material = $this->makeMaterial('Azúcar', ['stock_quantity' => '2.250', 'minimum_stock' => 1]);

        $component = Livewire::test(RawMaterials::class)
            ->assertSeeHtml('wire:click="openDetail('.$material->id.')"')
            ->call('openDetail', $material->id)
            ->assertSet('detailMaterialId', $material->id)
            ->assertSee('Detalle de la materia prima')
            ->assertSee('2.25 unidad');

        $component->call('openEdit', $material->id)
            ->assertSet('detailMaterialId', null)
            ->assertSet('editMaterialId', $material->id)
            ->assertDontSee('Detalle de la materia prima');

        $component->call('closeEdit')
            ->call('openDetail', $material->id)
            ->call('openRestock', $material->id)
            ->assertSet('detailMaterialId', null)
            ->assertSet('restockMaterialId', $material->id)
            ->assertDontSee('Detalle de la materia prima');

        $component->call('openDetail', $material->id)
            ->assertSet('detailMaterialId', $material->id)
            ->assertSet('restockMaterialId', null)
            ->assertSet('editMaterialId', null)
            ->assertDontSeeHtml('id="restock-title"');
    }

    public function test_opening_the_detail_from_an_open_edit_modal_leaves_only_the_detail(): void
    {
        $material = $this->makeMaterial('Azúcar', ['stock_quantity' => '2.250', 'minimum_stock' => 1]);

        Livewire::test(RawMaterials::class)
            ->call('openEdit', $material->id)
            ->assertSet('editMaterialId', $material->id)
            ->call('openDetail', $material->id)
            ->assertSet('editMaterialId', null)
            ->assertSet('editName', '')
            ->assertSet('restockMaterialId', null)
            ->assertSet('detailMaterialId', $material->id)
            ->assertSee('Detalle de la materia prima')
            ->assertDontSeeHtml('id="edit-title"');
    }

    public function test_material_row_announces_its_action_stock_and_minimum(): void
    {
        $this->makeMaterial('Azúcar', ['stock_quantity' => '2.250', 'minimum_stock' => 1]);

        $html = Livewire::test(RawMaterials::class)
            ->assertSeeHtml('aria-label="Ver detalle de Azúcar: 2.25 unidad en existencia, mínimo 1.00 unidad"')
            ->assertDontSeeHtml('role="progressbar"')
            ->html();

        $this->assertMatchesRegularExpression('/class="progress"[^>]*\saria-hidden="true"/', $html);
    }

    public function test_soft_deleted_material_remains_visible_when_used_by_a_recipe(): void
    {
        $material = $this->makeMaterial('Colorante');
        $variant = $this->makeVariant();
        $variant->recipeItems()->create([
            'raw_material_id' => $material->id,
            'quantity_required' => 1,
        ]);
        $material->delete();

        Livewire::test(Products::class)
            ->assertSee('Colorante')
            ->assertSee('Inactiva');
    }

    private function makeProduct(): Product
    {
        return Product::create(['name' => 'Chocobanano', 'slug' => 'chocobanano']);
    }

    private function makeVariant(?Product $product = null): ProductVariant
    {
        $product ??= $this->makeProduct();

        return $product->variants()->create([
            'name' => 'Maní',
            'sku' => 'CHO-MANI',
            'price_cents' => 2000,
        ]);
    }

    private function makeMaterial(string $name, array $attributes = []): RawMaterial
    {
        return RawMaterial::create(array_merge([
            'name' => $name,
            'unit' => 'unidad',
            'stock_quantity' => 0,
            'unit_cost_cents' => 300,
            'minimum_stock' => 1,
        ], $attributes));
    }
}
