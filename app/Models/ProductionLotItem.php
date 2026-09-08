<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionLotItem extends Model
{
    protected $fillable = [
        'production_lot_id',
        'product_variant_id',
        'quantity_produced',
        'quantity_available',
        'unit_cost_cents',
    ];

    protected function casts(): array
    {
        return [
            'quantity_produced' => 'integer',
            'quantity_available' => 'integer',
            'unit_cost_cents' => 'integer',
        ];
    }

    public function productionLot(): BelongsTo
    {
        return $this->belongsTo(ProductionLot::class)->withTrashed();
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class)->withTrashed();
    }

    public function saleAllocations(): HasMany
    {
        return $this->hasMany(SaleItemLot::class);
    }
}
