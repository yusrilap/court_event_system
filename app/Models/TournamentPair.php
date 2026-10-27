<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentPair extends Model
{
    protected $fillable = [
        'player_one_id',
        'player_two_id',
    ];

    protected function casts(): array
    {
        return [
            'player_one_id' => 'integer',
            'player_two_id' => 'integer',
            'tournament_group_id' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(
            EventCategory::class,
            'event_category_id'
        );
    }

    public function playerOne(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_one_id');
    }

    public function playerTwo(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_two_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(
            TournamentGroup::class,
            'tournament_group_id'
        );
    }
}