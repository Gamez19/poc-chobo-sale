<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecipeItem extends Model
{
    use SoftDeletes;

    protected $fillable = ['product_variant_id', 'raw_material_id', 'quantity_required'];

    protected function casts(): array
    {
        return ['quantity_required' => 'decimal:3'];
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class)->withTrashed();
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class)->withTrashed();
    }
}
