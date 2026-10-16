<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\RawMaterial;
use App\Services\ProductionService;
use App\Services\SalesService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(UserSeeder::class);

        $materials = collect([
            'Banano',
            'Chocolate para cobertura',
            'Maní triturado',
            'Chispitas',
            'Palillos',
            'Bolsas individuales',
        ])->map(fn (string $name): RawMaterial => RawMaterial::create(['name' => $name, 'unit' => 'unidad']));

        $purchase = Purchase::create([
            'purchased_at' => today()->subDays(2),
            'total_cents' => 33000,
            'notes' => '5 docenas de bananos y 3 bolsas de chocolate',
        ]);
        $purchase->rawMaterials()->sync($materials->take(2)->pluck('id'));

        $product = Product::create([
            'name' => 'Chocobanano',
            'slug' => 'chocobanano',
            'description' => 'Banano cubierto de chocolate, disponible en tres categorías.',
        ]);

        $variants = collect([
            ['key' => 'simple', 'name' => 'Simple', 'sku' => 'CHO-SIMPLE', 'price_cents' => 1500],
            ['key' => 'peanut', 'name' => 'Maní', 'sku' => 'CHO-MANI', 'price_cents' => 2000],
            ['key' => 'sprinkles', 'name' => 'Chispitas', 'sku' => 'CHO-CHISP', 'price_cents' => 2000],
        ])->mapWithKeys(function (array $data) use ($product) {
            $key = $data['key'];
            unset($data['key']);

            return [$key => $product->variants()->create($data)];
        });

        app(ProductionService::class)->createLot([
            'code' => 'LOTE-CHOCO-001',
            'produced_at' => today()->subDay(),
            'expires_at' => today()->addDays(6),
            'notes' => 'Primer lote de chocobananos',
        ], [
            $variants['simple']->id => 30,
            $variants['peanut']->id => 25,
            $variants['sprinkles']->id => 25,
        ]);

        app(SalesService::class)->record([
            $variants['simple']->id => 3,
            $variants['peanut']->id => 2,
            $variants['sprinkles']->id => 2,
        ], now()->subDay()->setTime(16, 30), 'Venta de demostración');

        app(SalesService::class)->record([
            $variants['simple']->id => 2,
            $variants['sprinkles']->id => 1,
        ], now()->setTime(15, 15), 'Venta de hoy');
    }
}
