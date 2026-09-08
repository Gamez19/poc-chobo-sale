<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductionLot extends Model
{
    use SoftDeletes;

    protected $fillable = ['code', 'produced_at', 'expires_at', 'status', 'notes'];

    protected function casts(): array
    {
        return [
            'produced_at' => 'date',
            'expires_at' => 'date',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductionLotItem::class);
    }
}
