<?php

namespace Tests\Feature;

use App\Livewire\ProductionLots;
use App\Models\Product;
use App\Models\ProductionLot;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Services\ProductionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ProductionDatesTest extends TestCase
{
    use RefreshDatabase;

    private function makeVariantWithRecipe(): ProductVariant
    {
        $material = RawMaterial::create([
            'name' => 'Banano',
            'unit' => 'unidad',
            'stock_quantity' => 10,
            'unit_cost_cents' => 300,
            'minimum_stock' => 2,
        ]);

        $variant = Product::create(['name' => 'Chocobanano', 'slug' => 'chocobanano'])
            ->variants()
            ->create(['name' => 'Simple', 'sku' => 'CHO-SIMPLE', 'price_cents' => 1500]);

        $variant->recipeItems()->create([
            'raw_material_id' => $material->id,
            'quantity_required' => 1,
        ]);

        return $variant;
    }

    public function test_livewire_rejects_a_future_produced_at_date(): void
    {
        $variant = $this->makeVariantWithRecipe();

        Livewire::test(ProductionLots::class)
            ->set('producedAt', today()->addDay()->format('Y-m-d'))
            ->set("variantQuantities.{$variant->id}", 2)
            ->call('createLot')
            ->assertHasErrors(['producedAt']);

        $this->assertSame(0, ProductionLot::query()->count());
        $this->assertEquals(10, RawMaterial::where('name', 'Banano')->firstOrFail()->stock_quantity);
    }

    public function test_livewire_future_produced_at_error_message_is_in_spanish(): void
    {
        $variant = $this->makeVariantWithRecipe();

        $component = Livewire::test(ProductionLots::class)
            ->set('producedAt', today()->addDays(5)->format('Y-m-d'))
            ->set("variantQuantities.{$variant->id}", 1)
            ->call('createLot');

        $this->assertSame(
            'La fecha de producción no puede ser futura.',
            $component->errors()->first('producedAt')
        );
    }

    public function test_service_rejects_a_future_produced_at_date_for_direct_callers(): void
    {
        $variant = $this->makeVariantWithRecipe();

        try {
            app(ProductionService::class)->createLot([
                'code' => 'LOTE-FUTURO',
                'produced_at' => today()->addDay()->format('Y-m-d'),
                'expires_at' => today()->addWeek()->format('Y-m-d'),
                'notes' => null,
            ], [$variant->id => 2]);

            $this->fail('Expected a ValidationException for a future produced_at date.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'La fecha de producción no puede ser futura.',
                $exception->validator->errors()->first('producedAt')
            );
        }

        $this->assertSame(0, ProductionLot::query()->count());
        $this->assertEquals(10, RawMaterial::where('name', 'Banano')->firstOrFail()->stock_quantity);
    }

    public function test_current_date_lot_is_still_created_and_consumes_materials(): void
    {
        $variant = $this->makeVariantWithRecipe();

        Livewire::test(ProductionLots::class)
            ->set('producedAt', today()->format('Y-m-d'))
            ->set('expiresAt', today()->addWeek()->format('Y-m-d'))
            ->set("variantQuantities.{$variant->id}", 3)
            ->call('createLot')
            ->assertHasNoErrors();

        $this->assertSame(1, ProductionLot::query()->count());
        $this->assertEquals(7, RawMaterial::where('name', 'Banano')->firstOrFail()->stock_quantity);
    }

    public function test_past_date_lot_remains_valid_for_direct_callers(): void
    {
        $variant = $this->makeVariantWithRecipe();

        $lot = app(ProductionService::class)->createLot([
            'code' => 'LOTE-PASADO',
            'produced_at' => today()->subDays(3)->format('Y-m-d'),
            'expires_at' => today()->addDays(2)->format('Y-m-d'),
            'notes' => null,
        ], [$variant->id => 1]);

        $this->assertSame('LOTE-PASADO', $lot->code);
        $this->assertEquals(9, RawMaterial::where('name', 'Banano')->firstOrFail()->stock_quantity);
    }
}
