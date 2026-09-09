<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_migration_can_resume_after_tables_were_created_before_a_failure(): void
    {
        Schema::table('production_lot_items', function (Blueprint $table): void {
            $table->dropIndex('idx_lot_items_in_stock');
        });

        $migration = require base_path('database/migrations/2026_09_08_000003_create_inventory_and_sales_tables.php');
        $migration->up();

        $this->assertTrue(Schema::hasIndex('production_lot_items', 'idx_lot_items_in_stock'));
    }

    public function test_soft_delete_migration_can_resume_after_partial_application(): void
    {
        $migration = require base_path('database/migrations/2026_09_08_000005_add_soft_deletes_to_catalog_and_lots.php');
        $migration->up();

        $this->assertTrue(Schema::hasColumn('products', 'deleted_at'));
        $this->assertTrue(Schema::hasIndex('products', 'products_slug_unique'));
        $index = DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'index' AND name = 'recipe_items_product_variant_id_raw_material_id_unique'");

        $this->assertNotNull($index);
        $this->assertStringContainsString('WHERE deleted_at IS NULL', $index->sql);
    }
}
