<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RawMaterial extends Model
{
    use SoftDeletes;

    public const UNITS = [
        'unidad',
        'gramos',
        'kilogramos',
        'kg',
        'mililitros',
        'paquete',
        'libra',
    ];

    protected $fillable = [
        'name',
        'unit',
        'stock_quantity',
        'unit_cost_cents',
        'minimum_stock',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'stock_quantity' => 'decimal:3',
            'minimum_stock' => 'decimal:3',
            'unit_cost_cents' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function recipeItems(): HasMany
    {
        return $this->hasMany(RecipeItem::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(RawMaterialMovement::class);
    }

    public function isLowStock(): bool
    {
        return (float) $this->stock_quantity <= (float) $this->minimum_stock;
    }
}
