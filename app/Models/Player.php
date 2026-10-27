<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Player extends Model
{
    protected $fillable = [
        'name',
        'nickname',
        'phone',
        'city',
        'notes',
    ];

    public function pairsAsPlayerOne(): HasMany
    {
        return $this->hasMany(
            TournamentPair::class,
            'player_one_id'
        );
    }

    public function pairsAsPlayerTwo(): HasMany
    {
        return $this->hasMany(
            TournamentPair::class,
            'player_two_id'
        );
    }
}