<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryScoringRule extends Model
{
    public const PHASE_LABELS = [
        'group' => 'Fase Grup',
        'round_of_16' => 'Babak 16 Besar',
        'quarterfinal' => 'Perempat Final',
        'semifinal' => 'Semifinal',
        'final' => 'Final',
        'third_place' => 'Perebutan Juara 3',
    ];

    public const FORMAT_LABELS = [
        'race_to' => 'Race to',
        'total_of' => 'Total of',
    ];

    protected $fillable = [
        'phase',
        'format',
        'game_count',
    ];

    protected function casts(): array
    {
        return [
            'event_category_id' => 'integer',
            'game_count' => 'integer',
            'locked_at' => 'immutable_datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(
            EventCategory::class,
            'event_category_id'
        );
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    public function formatLabel(): string
    {
        $label = self::FORMAT_LABELS[$this->format]
            ?? $this->format;

        return "{$label} {$this->game_count}";
    }
}