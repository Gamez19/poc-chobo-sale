<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Purchase extends Model
{
    protected $fillable = ['purchased_at', 'total_cents', 'notes'];

    protected function casts(): array
    {
        return [
            'purchased_at' => 'date',
            'total_cents' => 'integer',
        ];
    }

    /**
     * Checklist of raw materials bought in this purchase.
     */
    public function rawMaterials(): BelongsToMany
    {
        return $this->belongsToMany(RawMaterial::class)->withTrashed();
    }
}
