<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['products', 'product_variants', 'raw_materials', 'recipe_items', 'production_lots'] as $tableName) {
            if (! Schema::hasColumn($tableName, 'deleted_at')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->softDeletes();
                });
            }
        }

        $this->dropLegacyUniqueIndexes();
        $this->createSoftDeleteIndexes();
    }

    private function dropLegacyUniqueIndexes(): void
    {
        foreach ([
            ['table' => 'raw_materials', 'index' => 'raw_materials_name_unique'],
            ['table' => 'products', 'index' => 'products_slug_unique'],
            ['table' => 'product_variants', 'index' => 'product_variants_sku_unique'],
            ['table' => 'product_variants', 'index' => 'product_variants_product_id_name_unique'],
            ['table' => 'production_lots', 'index' => 'production_lots_code_unique'],
        ] as $definition) {
            if (! Schema::hasIndex($definition['table'], $definition['index'])) {
                continue;
            }

            Schema::table($definition['table'], function (Blueprint $table) use ($definition): void {
                $table->dropUnique($definition['index']);
            });
        }

        $recipeIndex = 'recipe_items_product_variant_id_raw_material_id_unique';

        if (! Schema::hasIndex('recipe_items', $recipeIndex)) {
            return;
        }

        if (DB::getDriverName() === 'mysql' && ! Schema::hasIndex('recipe_items', 'idx_recipe_items_product_variant_id')) {
            Schema::table('recipe_items', function (Blueprint $table): void {
                $table->index('product_variant_id', 'idx_recipe_items_product_variant_id');
            });
        }

        Schema::table('recipe_items', function (Blueprint $table) use ($recipeIndex): void {
            $table->dropUnique($recipeIndex);
        });
    }

    private function createSoftDeleteIndexes(): void
    {
        $definitions = DB::getDriverName() === 'mysql'
            ? [
                ['table' => 'raw_materials', 'index' => 'raw_materials_name_unique', 'statement' => 'CREATE UNIQUE INDEX raw_materials_name_unique ON raw_materials ((IF(deleted_at IS NULL, name, NULL)))'],
                ['table' => 'products', 'index' => 'products_slug_unique', 'statement' => 'CREATE UNIQUE INDEX products_slug_unique ON products ((IF(deleted_at IS NULL, slug, NULL)))'],
                ['table' => 'product_variants', 'index' => 'product_variants_sku_unique', 'statement' => 'CREATE UNIQUE INDEX product_variants_sku_unique ON product_variants ((IF(deleted_at IS NULL, sku, NULL)))'],
                ['table' => 'product_variants', 'index' => 'product_variants_product_id_name_unique', 'statement' => 'CREATE UNIQUE INDEX product_variants_product_id_name_unique ON product_variants ((IF(deleted_at IS NULL, product_id, NULL)), (IF(deleted_at IS NULL, name, NULL)))'],
                ['table' => 'recipe_items', 'index' => 'recipe_items_product_variant_id_raw_material_id_unique', 'statement' => 'CREATE UNIQUE INDEX recipe_items_product_variant_id_raw_material_id_unique ON recipe_items ((IF(deleted_at IS NULL, product_variant_id, NULL)), (IF(deleted_at IS NULL, raw_material_id, NULL)))'],
                ['table' => 'production_lots', 'index' => 'production_lots_code_unique', 'statement' => 'CREATE UNIQUE INDEX production_lots_code_unique ON production_lots ((IF(deleted_at IS NULL, code, NULL)))'],
            ]
            : [
                ['table' => 'raw_materials', 'index' => 'raw_materials_name_unique', 'statement' => 'CREATE UNIQUE INDEX raw_materials_name_unique ON raw_materials(name) WHERE deleted_at IS NULL'],
                ['table' => 'products', 'index' => 'products_slug_unique', 'statement' => 'CREATE UNIQUE INDEX products_slug_unique ON products(slug) WHERE deleted_at IS NULL'],
                ['table' => 'product_variants', 'index' => 'product_variants_sku_unique', 'statement' => 'CREATE UNIQUE INDEX product_variants_sku_unique ON product_variants(sku) WHERE deleted_at IS NULL'],
                ['table' => 'product_variants', 'index' => 'product_variants_product_id_name_unique', 'statement' => 'CREATE UNIQUE INDEX product_variants_product_id_name_unique ON product_variants(product_id, name) WHERE deleted_at IS NULL'],
                ['table' => 'recipe_items', 'index' => 'recipe_items_product_variant_id_raw_material_id_unique', 'statement' => 'CREATE UNIQUE INDEX recipe_items_product_variant_id_raw_material_id_unique ON recipe_items(product_variant_id, raw_material_id) WHERE deleted_at IS NULL'],
                ['table' => 'production_lots', 'index' => 'production_lots_code_unique', 'statement' => 'CREATE UNIQUE INDEX production_lots_code_unique ON production_lots(code) WHERE deleted_at IS NULL'],
            ];

        foreach ($definitions as $definition) {
            if (! Schema::hasIndex($definition['table'], $definition['index'])) {
                DB::statement($definition['statement']);
            }
        }
    }

    public function down(): void
    {
        $this->dropSoftDeleteIndexes();
        $this->restoreLegacyUniqueIndexes();

        if (DB::getDriverName() === 'mysql' && Schema::hasIndex('recipe_items', 'idx_recipe_items_product_variant_id')) {
            Schema::table('recipe_items', function (Blueprint $table): void {
                $table->dropIndex('idx_recipe_items_product_variant_id');
            });
        }

        foreach (['products', 'product_variants', 'raw_materials', 'recipe_items', 'production_lots'] as $tableName) {
            if (Schema::hasColumn($tableName, 'deleted_at')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->dropSoftDeletes();
                });
            }
        }
    }

    private function dropSoftDeleteIndexes(): void
    {
        foreach ([
            ['table' => 'raw_materials', 'index' => 'raw_materials_name_unique'],
            ['table' => 'products', 'index' => 'products_slug_unique'],
            ['table' => 'product_variants', 'index' => 'product_variants_sku_unique'],
            ['table' => 'product_variants', 'index' => 'product_variants_product_id_name_unique'],
            ['table' => 'production_lots', 'index' => 'production_lots_code_unique'],
        ] as $definition) {
            if (! Schema::hasIndex($definition['table'], $definition['index'])) {
                continue;
            }

            if (DB::getDriverName() === 'mysql') {
                DB::statement("DROP INDEX {$definition['index']} ON {$definition['table']}");
            } else {
                DB::statement("DROP INDEX IF EXISTS {$definition['index']}");
            }
        }
    }

    private function restoreLegacyUniqueIndexes(): void
    {
        foreach ([
            ['table' => 'raw_materials', 'index' => 'raw_materials_name_unique', 'columns' => ['name']],
            ['table' => 'products', 'index' => 'products_slug_unique', 'columns' => ['slug']],
            ['table' => 'product_variants', 'index' => 'product_variants_sku_unique', 'columns' => ['sku']],
            ['table' => 'product_variants', 'index' => 'product_variants_product_id_name_unique', 'columns' => ['product_id', 'name']],
            ['table' => 'production_lots', 'index' => 'production_lots_code_unique', 'columns' => ['code']],
            ['table' => 'recipe_items', 'index' => 'recipe_items_product_variant_id_raw_material_id_unique', 'columns' => ['product_variant_id', 'raw_material_id']],
        ] as $definition) {
            if (Schema::hasIndex($definition['table'], $definition['index'])) {
                continue;
            }

            Schema::table($definition['table'], function (Blueprint $table) use ($definition): void {
                $table->unique($definition['columns'], $definition['index']);
            });
        }
    }
};
