<?php

namespace Tests\Feature;

use App\Livewire\ProductionLots;
use App\Models\Product;
use App\Models\ProductionLot;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Services\ProductionService;
use App\Services\SalesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductionLotsTest extends TestCase
{
    use RefreshDatabase;

    public function test_editing_a_lot_updates_metadata_quantities_costs_and_material_stock_without_changing_sales_allocations(): void
    {
        [$lot, $variant, $material] = $this->makeLot();
        $sale = app(SalesService::class)->record([$variant->id => 1], now(), null);

        Livewire::test(ProductionLots::class)
            ->call('openEdit', $lot->id)
            ->set('editCode', 'LOTE-AJUSTADO')
            ->set('editProducedAt', today()->subDay()->format('Y-m-d'))
            ->set('editExpiresAt', today()->addWeek()->format('Y-m-d'))
            ->set('editNotes', 'Producción revisada')
            ->set("editVariantQuantities.{$variant->id}", 6)
            ->call('updateLot')
            ->assertHasNoErrors()
            ->assertSet('editLotId', null);

        $lot->refresh();
        $lotItem = $lot->items()->sole();
        $material->refresh();

        $this->assertSame('LOTE-AJUSTADO', $lot->code);
        $this->assertSame('Producción revisada', $lot->notes);
        $this->assertSame('open', $lot->status);
        $this->assertSame(6, $lotItem->quantity_produced);
        $this->assertSame(5, $lotItem->quantity_available);
        $this->assertSame(600, $lotItem->unit_cost_cents);
        $this->assertSame('8.000', $material->stock_quantity);
        $this->assertSame(1, $sale->items()->sole()->lotAllocations()->count());
        $this->assertDatabaseHas('raw_material_movements', [
            'raw_material_id' => $material->id,
            'type' => 'production_adjustment',
            'quantity' => -4,
            'reference_id' => $lot->id,
        ]);
    }

    public function test_editing_a_lot_to_the_sold_quantity_returns_materials_and_depletes_it(): void
    {
        [$lot, $variant, $material] = $this->makeLot();
        app(SalesService::class)->record([$variant->id => 1], now(), null);

        Livewire::test(ProductionLots::class)
            ->call('openEdit', $lot->id)
            ->set("editVariantQuantities.{$variant->id}", 1)
            ->call('updateLot')
            ->assertHasNoErrors();

        $lot->refresh();
        $lotItem = $lot->items()->sole();

        $this->assertSame('depleted', $lot->status);
        $this->assertSame(1, $lotItem->quantity_produced);
        $this->assertSame(0, $lotItem->quantity_available);
        $this->assertSame('18.000', $material->refresh()->stock_quantity);
        $this->assertDatabaseHas('raw_material_movements', [
            'raw_material_id' => $material->id,
            'type' => 'production_adjustment',
            'quantity' => 6,
            'reference_id' => $lot->id,
        ]);
    }

    public function test_editing_a_lot_rejects_a_quantity_below_its_sold_allocation(): void
    {
        [$lot, $variant, $material] = $this->makeLot();
        app(SalesService::class)->record([$variant->id => 3], now(), null);

        Livewire::test(ProductionLots::class)
            ->call('openEdit', $lot->id)
            ->set("editVariantQuantities.{$variant->id}", 2)
            ->call('updateLot')
            ->assertHasErrors("editVariantQuantities.{$variant->id}");

        $this->assertSame(4, $lot->items()->sole()->quantity_produced);
        $this->assertSame(1, $lot->items()->sole()->quantity_available);
        $this->assertSame('12.000', $material->refresh()->stock_quantity);
    }

    public function test_editing_a_lot_rejects_a_future_production_date(): void
    {
        [$lot] = $this->makeLot();

        Livewire::test(ProductionLots::class)
            ->call('openEdit', $lot->id)
            ->set('editProducedAt', today()->addDay()->format('Y-m-d'))
            ->call('updateLot')
            ->assertHasErrors(['editProducedAt' => 'before_or_equal']);

        $this->assertSame(today()->format('Y-m-d'), $lot->refresh()->produced_at->format('Y-m-d'));
    }

    public function test_editing_a_lot_rejects_new_variants_and_duplicate_active_codes(): void
    {
        [$lot, $variant] = $this->makeLot();
        $otherLot = app(ProductionService::class)->createLot([
            'code' => 'LOTE-EXISTENTE',
            'produced_at' => today()->format('Y-m-d'),
            'expires_at' => null,
            'notes' => null,
        ], [$variant->id => 1]);
        $otherVariant = $variant->product->variants()->create([
            'name' => 'Chispitas',
            'sku' => 'CHO-CHISPITAS',
            'price_cents' => 2200,
        ]);
        $otherVariant->recipeItems()->create([
            'raw_material_id' => $lot->items()->sole()->productVariant->recipeItems()->sole()->raw_material_id,
            'quantity_required' => 1,
        ]);

        Livewire::test(ProductionLots::class)
            ->call('openEdit', $lot->id)
            ->set('editCode', $otherLot->code)
            ->call('updateLot')
            ->assertHasErrors('editCode')
            ->set('editCode', $lot->code)
            ->set("editVariantQuantities.{$otherVariant->id}", 1)
            ->call('updateLot')
            ->assertHasErrors('editVariantQuantities');

        $this->assertSame(1, $lot->items()->count());
        $this->assertSame(4, $lot->items()->sole()->quantity_produced);
    }

    /**
     * @return array{0: ProductionLot, 1: ProductVariant, 2: RawMaterial}
     */
    private function makeLot(): array
    {
        $material = RawMaterial::create([
            'name' => 'Banano',
            'unit' => 'unidad',
            'stock_quantity' => 20,
            'unit_cost_cents' => 300,
            'minimum_stock' => 2,
        ]);
        $variant = Product::create(['name' => 'Chocobanano', 'slug' => 'chocobanano'])
            ->variants()
            ->create(['name' => 'Simple', 'sku' => 'CHO-SIMPLE', 'price_cents' => 1500]);
        $variant->recipeItems()->create([
            'raw_material_id' => $material->id,
            'quantity_required' => 2,
        ]);

        $lot = app(ProductionService::class)->createLot([
            'code' => 'LOTE-ORIGINAL',
            'produced_at' => today()->format('Y-m-d'),
            'expires_at' => today()->addWeek()->format('Y-m-d'),
            'notes' => 'Producción inicial',
        ], [$variant->id => 4]);

        return [$lot, $variant, $material];
    }
}
