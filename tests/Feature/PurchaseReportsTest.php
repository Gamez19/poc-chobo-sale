<?php

namespace Tests\Feature;

use App\Livewire\PurchaseReports;
use App\Livewire\RawMaterials;
use App\Models\Purchase;
use App\Models\RawMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PurchaseReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_history_lists_only_the_latest_five_purchases(): void
    {
        foreach (range(1, 6) as $day) {
            Purchase::create([
                'purchased_at' => today()->subDays(7 - $day),
                'total_cents' => $day * 1000,
                'notes' => "Compra numero {$day}",
            ]);
        }

        Livewire::test(RawMaterials::class)
            ->assertSee('Últimas 5 compras')
            ->assertSee('Compra numero 6')
            ->assertSee('Compra numero 2')
            ->assertDontSee('Compra numero 1')
            ->assertSeeHtml(route('reports.purchases'));
    }

    public function test_purchase_report_totals_purchases_inside_the_date_range(): void
    {
        $banana = RawMaterial::create(['name' => 'Banano', 'unit' => 'unidad']);
        $chocolate = RawMaterial::create(['name' => 'Chocolate', 'unit' => 'unidad']);
        $inRange = Purchase::create(['purchased_at' => '2026-10-05', 'total_cents' => 15000, 'notes' => '5 docenas de bananos']);
        $inRange->rawMaterials()->sync([$banana->id]);
        $alsoInRange = Purchase::create(['purchased_at' => '2026-10-10', 'total_cents' => 18000, 'notes' => '3 bolsas de chocolate']);
        $alsoInRange->rawMaterials()->sync([$chocolate->id]);
        Purchase::create(['purchased_at' => '2026-09-30', 'total_cents' => 99900, 'notes' => 'Fuera de rango']);

        Livewire::test(PurchaseReports::class)
            ->set('dateFrom', '2026-10-01')
            ->set('dateTo', '2026-10-16')
            ->assertHasNoErrors()
            ->assertViewHas('totalSpentCents', 33000)
            ->assertViewHas('purchasesCount', 2)
            ->assertViewHas('averageCents', 16500)
            ->assertSee('C$ 330.00')
            ->assertSee('5 docenas de bananos')
            ->assertSee('3 bolsas de chocolate')
            ->assertDontSee('Fuera de rango');
    }

    public function test_purchase_report_filters_by_raw_material(): void
    {
        $banana = RawMaterial::create(['name' => 'Banano', 'unit' => 'unidad']);
        $chocolate = RawMaterial::create(['name' => 'Chocolate', 'unit' => 'unidad']);
        Purchase::create(['purchased_at' => today(), 'total_cents' => 15000, 'notes' => 'Solo bananos'])
            ->rawMaterials()->sync([$banana->id]);
        Purchase::create(['purchased_at' => today(), 'total_cents' => 18000, 'notes' => 'Solo chocolate'])
            ->rawMaterials()->sync([$chocolate->id]);

        Livewire::test(PurchaseReports::class)
            ->set('rawMaterialId', (string) $chocolate->id)
            ->assertViewHas('totalSpentCents', 18000)
            ->assertViewHas('purchasesCount', 1)
            ->assertSee('Solo chocolate')
            ->assertDontSee('Solo bananos');
    }

    public function test_purchase_report_rejects_invalid_and_inverted_ranges(): void
    {
        Purchase::create(['purchased_at' => today(), 'total_cents' => 15000, 'notes' => 'Compra de hoy']);

        Livewire::test(PurchaseReports::class)
            ->set('dateFrom', '2026-02-30')
            ->assertHasErrors('dateFrom')
            ->assertSee('Ingresa un rango de fechas válido')
            ->assertViewHas('purchasesCount', 0)
            ->assertDontSee('Compra de hoy')
            ->set('dateFrom', today()->format('Y-m-d'))
            ->set('dateTo', today()->subDay()->format('Y-m-d'))
            ->assertHasErrors('dateFrom')
            ->assertSee('La fecha inicial no puede ser posterior a la fecha final.')
            ->assertViewHas('purchasesCount', 0);
    }

    public function test_reports_menu_groups_sales_and_purchases_reports(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('reports.purchases'))
            ->assertOk()
            ->assertSeeLivewire(PurchaseReports::class)
            ->assertSeeInOrder(['Reportes', 'Ventas', 'Compras', 'Ganancias'])
            ->assertSee('href="'.route('reports').'"', false)
            ->assertSee('href="'.route('reports.purchases').'"', false);

        $this->actingAs($user)
            ->get(route('reports'))
            ->assertOk()
            ->assertSee('Reporte de ventas');
    }
}
