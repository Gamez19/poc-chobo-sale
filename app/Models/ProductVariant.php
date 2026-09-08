<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use SoftDeletes;

    protected $fillable = ['product_id', 'name', 'sku', 'price_cents', 'active'];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function recipeItems(): HasMany
    {
        return $this->hasMany(RecipeItem::class);
    }

    public function lotItems(): HasMany
    {
        return $this->hasMany(ProductionLotItem::class);
    }

    public function availableLotItems(): HasMany
    {
        return $this->lotItems()
            ->where('quantity_available', '>', 0)
            ->whereHas('productionLot', fn ($query) => $query
                ->whereNull('deleted_at')
                ->where('status', 'open')
                ->where(fn ($lotQuery) => $lotQuery
                    ->whereNull('expires_at')
                    ->orWhereDate('expires_at', '>=', today())));
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
}
