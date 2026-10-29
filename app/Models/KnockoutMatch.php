<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class KnockoutMatch extends Model
{
    protected $fillable = [
        'round',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(
            EventCategory::class,
            'event_category_id'
        );
    }

    public function slots(): HasMany
    {
        return $this->hasMany(
            KnockoutSlot::class,
            'knockout_match_id'
        );
    }

    public function destinationSlots(): HasMany
    {
        return $this->hasMany(
            KnockoutSlot::class,
            'source_match_id'
        );
    }

    public function schedule(): HasOne
    {
        return $this->hasOne(MatchSchedule::class, 'knockout_match_id');
    }
}