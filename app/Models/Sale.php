<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    protected $fillable = ['number', 'sold_at', 'total_cents', 'notes'];

    protected function casts(): array
    {
        return [
            'sold_at' => 'datetime',
            'total_cents' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
}
