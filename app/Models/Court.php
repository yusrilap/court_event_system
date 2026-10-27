<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Court extends Model
{
    protected $fillable = [
        'location_id',
        'name',
        'sport',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'location_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}