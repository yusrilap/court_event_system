<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnockoutSlot extends Model
{
    protected $fillable = [
        'side',
        'source_type',
        'source_group_id',
        'source_rank',
        'source_match_id',
    ];

    protected function casts(): array
    {
        return [
            'source_group_id' => 'integer',
            'source_rank' => 'integer',
            'source_match_id' => 'integer',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(
            KnockoutMatch::class,
            'knockout_match_id'
        );
    }

    public function sourceGroup(): BelongsTo
    {
        return $this->belongsTo(
            TournamentGroup::class,
            'source_group_id'
        );
    }

    public function sourceMatch(): BelongsTo
    {
        return $this->belongsTo(
            KnockoutMatch::class,
            'source_match_id'
        );
    }
}