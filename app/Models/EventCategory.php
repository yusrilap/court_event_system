<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class EventCategory extends Model
{
    protected $fillable = [
        'name',
        'description',
        'slot_duration_minutes',
        'pair_count',
        'group_count',
        'qualifiers_per_group',
        'has_third_place_match',
    ];

    protected function casts(): array
    {
        return [
            'slot_duration_minutes' => 'integer',
            'pair_count' => 'integer',
            'group_count' => 'integer',
            'qualifiers_per_group' => 'integer',
            'has_third_place_match' => 'boolean',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function pairs(): HasMany
    {
        return $this->hasMany(
            TournamentPair::class,
            'event_category_id'
        );
    }

    public function groups(): HasMany
    {
        return $this->hasMany(
            TournamentGroup::class,
            'event_category_id'
        );
    }

    public function knockoutMatches(): HasMany
    {
        return $this->hasMany(
            KnockoutMatch::class,
            'event_category_id'
        );
    }

    public function groupMatches(): HasManyThrough
    {
        return $this->hasManyThrough(
            GroupMatch::class,
            TournamentGroup::class,
            'event_category_id',
            'tournament_group_id',
            'id',
            'id'
        );
    }
}