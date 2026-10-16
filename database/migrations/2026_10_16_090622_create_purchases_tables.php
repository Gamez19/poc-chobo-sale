<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->date('purchased_at');
            $table->unsignedInteger('total_cents');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('purchased_at', 'idx_purchases_purchased_at');
        });

        Schema::create('purchase_raw_material', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('raw_material_id')->constrained();
            $table->unique(['purchase_id', 'raw_material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_raw_material');
        Schema::dropIfExists('purchases');
    }
};
