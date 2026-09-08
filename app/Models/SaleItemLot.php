<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItemLot extends Model
{
    protected $fillable = ['sale_item_id', 'production_lot_item_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function productionLotItem(): BelongsTo
    {
        return $this->belongsTo(ProductionLotItem::class);
    }
}
