<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Services\ProductionService;
use App\Services\SalesService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $materials = collect([
            ['key' => 'banana', 'name' => 'Banano', 'unit' => 'unidad', 'stock_quantity' => 180, 'unit_cost_cents' => 300, 'minimum_stock' => 30],
            ['key' => 'chocolate', 'name' => 'Chocolate para cobertura', 'unit' => 'gramos', 'stock_quantity' => 9000, 'unit_cost_cents' => 18, 'minimum_stock' => 1200],
            ['key' => 'peanut', 'name' => 'Maní triturado', 'unit' => 'gramos', 'stock_quantity' => 2000, 'unit_cost_cents' => 12, 'minimum_stock' => 300],
            ['key' => 'sprinkles', 'name' => 'Chispitas', 'unit' => 'gramos', 'stock_quantity' => 1500, 'unit_cost_cents' => 15, 'minimum_stock' => 250],
            ['key' => 'stick', 'name' => 'Palillos', 'unit' => 'unidad', 'stock_quantity' => 200, 'unit_cost_cents' => 25, 'minimum_stock' => 40],
            ['key' => 'bag', 'name' => 'Bolsas individuales', 'unit' => 'unidad', 'stock_quantity' => 180, 'unit_cost_cents' => 50, 'minimum_stock' => 30],
        ])->mapWithKeys(function (array $data) {
            $key = $data['key'];
            unset($data['key']);

            return [$key => RawMaterial::create($data)];
        });

        foreach ($materials as $material) {
            $material->movements()->create([
                'type' => 'initial',
                'quantity' => $material->stock_quantity,
                'unit_cost_cents' => $material->unit_cost_cents,
                'occurred_at' => now()->subDays(3),
                'notes' => 'Inventario de demostración',
            ]);
        }

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

        $baseRecipe = [
            $materials['banana']->id => 1,
            $materials['chocolate']->id => 40,
            $materials['stick']->id => 1,
            $materials['bag']->id => 1,
        ];

        $this->attachRecipe($variants['simple'], $baseRecipe);
        $this->attachRecipe($variants['peanut'], $baseRecipe + [$materials['peanut']->id => 10]);
        $this->attachRecipe($variants['sprinkles'], $baseRecipe + [$materials['sprinkles']->id => 8]);

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

    /**
     * @param  array<int, int|float>  $recipe
     */
    private function attachRecipe(ProductVariant $variant, array $recipe): void
    {
        foreach ($recipe as $materialId => $quantity) {
            $variant->recipeItems()->create([
                'raw_material_id' => $materialId,
                'quantity_required' => $quantity,
            ]);
        }
    }
}
