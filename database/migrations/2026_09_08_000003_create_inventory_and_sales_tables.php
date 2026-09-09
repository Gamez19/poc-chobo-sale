<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (collect([
            'raw_materials',
            'products',
            'product_variants',
            'recipe_items',
            'production_lots',
            'production_lot_items',
            'raw_material_movements',
            'sales',
            'sale_items',
            'sale_item_lots',
        ])->every(fn (string $tableName): bool => Schema::hasTable($tableName))) {
            $this->ensureStockIndex();

            return;
        }

        Schema::create('raw_materials', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('unit', 30);
            $table->decimal('stock_quantity', 12, 3)->default(0);
            $table->unsignedInteger('unit_cost_cents')->default(0);
            $table->decimal('minimum_stock', 12, 3)->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['active', 'name'], 'idx_raw_materials_active_name');
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sku')->unique();
            $table->unsignedInteger('price_cents');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['product_id', 'name']);
            $table->index(['product_id', 'active'], 'idx_variants_product_active');
        });

        Schema::create('recipe_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('raw_material_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_required', 12, 3);
            $table->timestamps();
            $table->unique(['product_variant_id', 'raw_material_id']);
        });

        Schema::create('production_lots', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->date('produced_at');
            $table->date('expires_at')->nullable();
            $table->string('status', 20)->default('open');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['produced_at', 'status'], 'idx_lots_date_status');
        });

        Schema::create('production_lot_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_lot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity_produced');
            $table->unsignedInteger('quantity_available');
            $table->unsignedInteger('unit_cost_cents')->default(0);
            $table->timestamps();
            $table->unique(['production_lot_id', 'product_variant_id']);
            $table->index(['product_variant_id', 'quantity_available'], 'idx_lot_items_variant_available');
        });

        Schema::create('raw_material_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raw_material_id')->constrained()->restrictOnDelete();
            $table->string('type', 30);
            $table->decimal('quantity', 12, 3);
            $table->unsignedInteger('unit_cost_cents')->nullable();
            $table->nullableMorphs('reference');
            $table->timestamp('occurred_at');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['raw_material_id', 'occurred_at'], 'idx_material_movements_date');
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->timestamp('sold_at');
            $table->unsignedInteger('total_cents');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('sold_at', 'idx_sales_sold_at');
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_price_cents');
            $table->unsignedInteger('subtotal_cents');
            $table->timestamps();
            $table->index(['sale_id', 'product_variant_id'], 'idx_sale_items_sale_variant');
        });

        Schema::create('sale_item_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_lot_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();
            $table->unique(['sale_item_id', 'production_lot_item_id']);
            $table->index('production_lot_item_id', 'idx_sale_lots_lot_item');
        });

        $this->ensureStockIndex();
    }

    private function ensureStockIndex(): void
    {
        if (Schema::hasIndex('production_lot_items', 'idx_lot_items_in_stock')) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('CREATE INDEX idx_lot_items_in_stock ON production_lot_items(product_variant_id, production_lot_id) WHERE quantity_available > 0');
            DB::statement('PRAGMA optimize');

            return;
        }

        Schema::table('production_lot_items', function (Blueprint $table): void {
            $table->index(
                ['product_variant_id', 'production_lot_id', 'quantity_available'],
                'idx_lot_items_in_stock',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_item_lots');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('raw_material_movements');
        Schema::dropIfExists('production_lot_items');
        Schema::dropIfExists('production_lots');
        Schema::dropIfExists('recipe_items');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('raw_materials');
    }
};
