<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RawMaterialMovement extends Model
{
    protected $fillable = [
        'raw_material_id',
        'type',
        'quantity',
        'unit_cost_cents',
        'reference_type',
        'reference_id',
        'occurred_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost_cents' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
