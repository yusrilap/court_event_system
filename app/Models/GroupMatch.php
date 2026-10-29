<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupMatch extends Model
{
    protected $fillable = [
        'pair_one_id',
        'pair_two_id',
        'round_number',
    ];

    protected function casts(): array
    {
        return [
            'pair_one_id' => 'integer',
            'pair_two_id' => 'integer',
            'round_number' => 'integer',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(
            TournamentGroup::class,
            'tournament_group_id'
        );
    }

    public function pairOne(): BelongsTo
    {
        return $this->belongsTo(
            TournamentPair::class,
            'pair_one_id'
        );
    }

    public function pairTwo(): BelongsTo
    {
        return $this->belongsTo(
            TournamentPair::class,
            'pair_two_id'
        );
    }
}