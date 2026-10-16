<?php

namespace Tests\Feature;

use App\Livewire\RawMaterials;
use App\Models\Purchase;
use App\Models\RawMaterial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PurchasesTest extends TestCase
{
    use RefreshDatabase;

    public function test_raw_material_is_registered_by_name_only(): void
    {
        Livewire::test(RawMaterials::class)
            ->set('name', '  Chocolate  ')
            ->call('createMaterial')
            ->assertHasNoErrors()
            ->assertSee('Chocolate');

        $material = RawMaterial::sole();
        $this->assertSame('Chocolate', $material->name);
        $this->assertTrue($material->active);
        $this->assertSame(0, $material->movements()->count());
    }

    public function test_add_material_button_opens_a_modal_that_closes_after_saving(): void
    {
        Livewire::test(RawMaterials::class)
            ->assertSeeHtml('wire:click="openCreate"')
            ->assertDontSeeHtml('id="create-title"')
            ->call('openCreate')
            ->assertSet('isCreatingMaterial', true)
            ->assertSeeHtml('id="create-title"')
            ->set('name', 'Mani')
            ->call('createMaterial')
            ->assertHasNoErrors()
            ->assertSet('isCreatingMaterial', false)
            ->assertSet('name', '')
            ->assertDontSeeHtml('id="create-title"');

        $this->assertSame(['Mani'], RawMaterial::pluck('name')->all());
    }

    public function test_add_material_modal_stays_open_on_validation_error(): void
    {
        Livewire::test(RawMaterials::class)
            ->call('openCreate')
            ->set('name', '')
            ->call('createMaterial')
            ->assertHasErrors(['name' => 'required'])
            ->assertSet('isCreatingMaterial', true)
            ->call('closeCreate')
            ->assertSet('isCreatingMaterial', false)
            ->assertHasNoErrors();

        $this->assertSame(0, RawMaterial::count());
    }

    public function test_raw_material_name_is_required_and_unique(): void
    {
        RawMaterial::create(['name' => 'Banano', 'unit' => 'unidad']);

        Livewire::test(RawMaterials::class)
            ->set('name', '')
            ->call('createMaterial')
            ->assertHasErrors(['name' => 'required'])
            ->set('name', 'Banano')
            ->call('createMaterial')
            ->assertHasErrors(['name' => 'unique']);

        $this->assertSame(1, RawMaterial::count());
    }

    public function test_raw_material_can_be_renamed(): void
    {
        $material = RawMaterial::create(['name' => 'Mani', 'unit' => 'unidad']);

        Livewire::test(RawMaterials::class)
            ->call('openEdit', $material->id)
            ->assertSet('editName', 'Mani')
            ->set('editName', 'Maní')
            ->call('updateMaterial')
            ->assertHasNoErrors()
            ->assertSet('editMaterialId', null);

        $this->assertSame('Maní', $material->fresh()->name);
    }

    public function test_purchase_records_the_total_spent_and_the_checklist_of_materials(): void
    {
        $banana = RawMaterial::create(['name' => 'Banano', 'unit' => 'unidad']);
        $chocolate = RawMaterial::create(['name' => 'Chocolate', 'unit' => 'unidad']);
        $peanut = RawMaterial::create(['name' => 'Maní', 'unit' => 'unidad']);

        Livewire::test(RawMaterials::class)
            ->assertSet('purchasedAt', today()->format('Y-m-d'))
            ->set('purchaseTotal', '330')
            ->set('purchaseMaterialIds', [$banana->id, $chocolate->id])
            ->set('purchaseNotes', '5 docenas de bananos y 3 bolsas de chocolate')
            ->call('createPurchase')
            ->assertHasNoErrors()
            ->assertSet('purchaseTotal', '')
            ->assertSet('purchaseMaterialIds', [])
            ->assertSee('C$ 330.00')
            ->assertSee('Banano, Chocolate');

        $purchase = Purchase::sole();
        $this->assertSame(33000, $purchase->total_cents);
        $this->assertSame(today()->format('Y-m-d'), $purchase->purchased_at->format('Y-m-d'));
        $this->assertSame('5 docenas de bananos y 3 bolsas de chocolate', $purchase->notes);
        $this->assertEqualsCanonicalizing(
            [$banana->id, $chocolate->id],
            $purchase->rawMaterials->pluck('id')->all(),
        );
        $this->assertFalse($purchase->rawMaterials->contains($peanut));
    }

    public function test_purchase_rejects_invalid_input_without_saving(): void
    {
        $banana = RawMaterial::create(['name' => 'Banano', 'unit' => 'unidad']);

        Livewire::test(RawMaterials::class)
            ->set('purchaseTotal', '0')
            ->set('purchasedAt', today()->addDay()->format('Y-m-d'))
            ->set('purchaseMaterialIds', [$banana->id, 999])
            ->call('createPurchase')
            ->assertHasErrors(['purchaseTotal', 'purchasedAt', 'purchaseMaterialIds.1'])
            ->assertSee('La fecha de compra no puede ser futura.');

        $this->assertSame(0, Purchase::count());
    }

    public function test_purchase_can_be_deleted(): void
    {
        $purchase = Purchase::create(['purchased_at' => today(), 'total_cents' => 15000]);

        Livewire::test(RawMaterials::class)
            ->assertSee('C$ 150.00')
            ->call('deletePurchase', $purchase->id)
            ->assertDontSee('C$ 150.00');

        $this->assertSame(0, Purchase::count());
    }

    public function test_page_no_longer_shows_stock_cost_or_restock_controls(): void
    {
        RawMaterial::create(['name' => 'Banano', 'unit' => 'unidad', 'stock_quantity' => 5, 'minimum_stock' => 10]);

        Livewire::test(RawMaterials::class)
            ->assertSee('Banano')
            ->assertSee('Registrar compra')
            ->assertDontSee('Agregar entrada')
            ->assertDontSee('Nivel mínimo')
            ->assertDontSee('Costo por unidad');
    }
}
