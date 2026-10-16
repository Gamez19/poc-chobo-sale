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

    public function test_variant_deletion_is_soft_and_restore_recovers_it(): void
    {
        $variant = $this->makeVariant();

        Livewire::test(Products::class)
            ->call('removeVariant', $variant->id)
            ->assertHasNoErrors()
            ->assertSet('statusMessage', 'Variante eliminada y conservada en el historial.');

        $this->assertSoftDeleted('product_variants', ['id' => $variant->id]);

        Livewire::test(Products::class)
            ->call('restoreVariant', $variant->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'active' => true,
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
