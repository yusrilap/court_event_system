<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Event extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'sport',
        'event_date',
        'location',
        'description',
        'technical_meeting',
        'rules',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'is_published' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function categories(): HasMany
    {
        return $this->hasMany(EventCategory::class);
    }

    public function courts(): BelongsToMany
    {
        return $this->belongsToMany(
            Court::class,
            'event_court'
        )->withTimestamps();
    }
}