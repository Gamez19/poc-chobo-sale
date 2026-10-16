<?php

namespace Tests\Feature;

use App\Livewire\ProductionLots;
use App\Models\Product;
use App\Models\ProductionLot;
use App\Models\ProductVariant;
use App\Services\ProductionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ProductionDatesTest extends TestCase
{
    use RefreshDatabase;

    private function makeVariant(): ProductVariant
    {
        return Product::create(['name' => 'Chocobanano', 'slug' => 'chocobanano'])
            ->variants()
            ->create(['name' => 'Simple', 'sku' => 'CHO-SIMPLE', 'price_cents' => 1500]);
    }

    public function test_livewire_rejects_a_future_produced_at_date(): void
    {
        $variant = $this->makeVariant();

        Livewire::test(ProductionLots::class)
            ->set('producedAt', today()->addDay()->format('Y-m-d'))
            ->set("variantQuantities.{$variant->id}", 2)
            ->call('createLot')
            ->assertHasErrors(['producedAt']);

        $this->assertSame(0, ProductionLot::query()->count());
    }

    public function test_livewire_future_produced_at_error_message_is_in_spanish(): void
    {
        $variant = $this->makeVariant();

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
        $variant = $this->makeVariant();

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
    }

    public function test_current_date_lot_is_still_created(): void
    {
        $variant = $this->makeVariant();

        Livewire::test(ProductionLots::class)
            ->set('producedAt', today()->format('Y-m-d'))
            ->set('expiresAt', today()->addWeek()->format('Y-m-d'))
            ->set("variantQuantities.{$variant->id}", 3)
            ->call('createLot')
            ->assertHasNoErrors();

        $this->assertSame(1, ProductionLot::query()->count());
    }

    public function test_past_date_lot_remains_valid_for_direct_callers(): void
    {
        $variant = $this->makeVariant();

        $lot = app(ProductionService::class)->createLot([
            'code' => 'LOTE-PASADO',
            'produced_at' => today()->subDays(3)->format('Y-m-d'),
            'expires_at' => today()->addDays(2)->format('Y-m-d'),
            'notes' => null,
        ], [$variant->id => 1]);

        $this->assertSame('LOTE-PASADO', $lot->code);
    }
}
