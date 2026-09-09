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
            Schema::table($tableName, function (Blueprint $table): void {
                $table->softDeletes();
            });
        }

        Schema::table('raw_materials', function (Blueprint $table): void {
            $table->dropUnique('raw_materials_name_unique');
        });
        Schema::table('products', function (Blueprint $table): void {
            $table->dropUnique('products_slug_unique');
        });
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropUnique('product_variants_sku_unique');
            $table->dropUnique('product_variants_product_id_name_unique');
        });
        Schema::table('recipe_items', function (Blueprint $table): void {
            $table->dropUnique('recipe_items_product_variant_id_raw_material_id_unique');
        });
        Schema::table('production_lots', function (Blueprint $table): void {
            $table->dropUnique('production_lots_code_unique');
        });

        $this->createSoftDeleteIndexes();
    }

    private function createSoftDeleteIndexes(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('CREATE UNIQUE INDEX raw_materials_name_unique ON raw_materials ((IF(deleted_at IS NULL, name, NULL)))');
            DB::statement('CREATE UNIQUE INDEX products_slug_unique ON products ((IF(deleted_at IS NULL, slug, NULL)))');
            DB::statement('CREATE UNIQUE INDEX product_variants_sku_unique ON product_variants ((IF(deleted_at IS NULL, sku, NULL)))');
            DB::statement('CREATE UNIQUE INDEX product_variants_product_id_name_unique ON product_variants ((IF(deleted_at IS NULL, product_id, NULL)), (IF(deleted_at IS NULL, name, NULL)))');
            DB::statement('CREATE UNIQUE INDEX recipe_items_product_variant_id_raw_material_id_unique ON recipe_items ((IF(deleted_at IS NULL, product_variant_id, NULL)), (IF(deleted_at IS NULL, raw_material_id, NULL)))');
            DB::statement('CREATE UNIQUE INDEX production_lots_code_unique ON production_lots ((IF(deleted_at IS NULL, code, NULL)))');

            return;
        }

        DB::statement('CREATE UNIQUE INDEX raw_materials_name_unique ON raw_materials(name) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX products_slug_unique ON products(slug) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX product_variants_sku_unique ON product_variants(sku) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX product_variants_product_id_name_unique ON product_variants(product_id, name) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX recipe_items_product_variant_id_raw_material_id_unique ON recipe_items(product_variant_id, raw_material_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX production_lots_code_unique ON production_lots(code) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        $this->dropSoftDeleteIndexes();

        Schema::table('raw_materials', function (Blueprint $table): void {
            $table->unique('name');
        });
        Schema::table('products', function (Blueprint $table): void {
            $table->unique('slug');
        });
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->unique('sku');
            $table->unique(['product_id', 'name']);
        });
        Schema::table('recipe_items', function (Blueprint $table): void {
            $table->unique(['product_variant_id', 'raw_material_id']);
        });
        Schema::table('production_lots', function (Blueprint $table): void {
            $table->unique('code');
        });

        foreach (['products', 'product_variants', 'raw_materials', 'recipe_items', 'production_lots'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropSoftDeletes();
            });
        }
    }

    private function dropSoftDeleteIndexes(): void
    {
        if (DB::getDriverName() === 'mysql') {
            foreach ([
                'raw_materials_name_unique' => 'raw_materials',
                'products_slug_unique' => 'products',
                'product_variants_sku_unique' => 'product_variants',
                'product_variants_product_id_name_unique' => 'product_variants',
                'recipe_items_product_variant_id_raw_material_id_unique' => 'recipe_items',
                'production_lots_code_unique' => 'production_lots',
            ] as $index => $table) {
                DB::statement("DROP INDEX {$index} ON {$table}");
            }

            return;
        }

        DB::statement('DROP INDEX IF EXISTS raw_materials_name_unique');
        DB::statement('DROP INDEX IF EXISTS products_slug_unique');
        DB::statement('DROP INDEX IF EXISTS product_variants_sku_unique');
        DB::statement('DROP INDEX IF EXISTS product_variants_product_id_name_unique');
        DB::statement('DROP INDEX IF EXISTS recipe_items_product_variant_id_raw_material_id_unique');
        DB::statement('DROP INDEX IF EXISTS production_lots_code_unique');
    }
};
