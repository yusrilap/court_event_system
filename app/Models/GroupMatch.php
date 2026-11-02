<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
            'score_one' => 'integer',
            'score_two' => 'integer',
            'result_game_count' => 'integer',
            'result_recorded_at' => 'immutable_datetime',
            'result_recorded_by' => 'integer',
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

    public function schedule(): HasOne
    {
        return $this->hasOne(MatchSchedule::class, 'group_match_id');
    }

    public function resultRecorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'result_recorded_by');
    }
}