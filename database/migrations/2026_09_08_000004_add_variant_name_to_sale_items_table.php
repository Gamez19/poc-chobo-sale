<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->string('variant_name')->nullable()->after('product_variant_id');
        });

        DB::table('sale_items')
            ->whereNull('variant_name')
            ->update([
                'variant_name' => DB::raw('(select coalesce(product_variants.name, \'\') from product_variants where product_variants.id = sale_items.product_variant_id)'),
            ]);

        DB::table('sale_items')->whereNull('variant_name')->update(['variant_name' => '']);

        Schema::table('sale_items', function (Blueprint $table) {
            $table->string('variant_name')->nullable(false)->default('')->change();
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('variant_name');
        });
    }
};
