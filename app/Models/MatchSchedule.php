<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class MatchSchedule extends Model
{
    protected $fillable = [
        'group_match_id',
        'knockout_match_id',
        'court_id',
        'planned_start_at',
        'planned_end_at',
    ];

    protected function casts(): array
    {
        return [
            'group_match_id' => 'integer',
            'knockout_match_id' => 'integer',
            'court_id' => 'integer',
            'planned_start_at' => 'immutable_datetime',
            'planned_end_at' => 'immutable_datetime',
            'actual_start_at' => 'immutable_datetime',
            'actual_end_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (MatchSchedule $schedule): void {
            $hasGroupMatch = $schedule->group_match_id !== null;
            $hasKnockoutMatch = $schedule->knockout_match_id !== null;

            if ($hasGroupMatch === $hasKnockoutMatch) {
                throw ValidationException::withMessages([
                    'group_match_id' =>
                        'Jadwal harus terhubung ke tepat satu pertandingan: grup atau gugur.',
                ]);
            }

            if (
                $schedule->planned_start_at === null ||
                $schedule->planned_end_at === null
            ) {
                throw ValidationException::withMessages([
                    'planned_start_at' =>
                        'Waktu mulai dan selesai rencana wajib diisi.',
                ]);
            }

            if (
                $schedule->planned_end_at->lessThanOrEqualTo(
                    $schedule->planned_start_at
                )
            ) {
                throw ValidationException::withMessages([
                    'planned_end_at' =>
                        'Waktu selesai rencana harus setelah waktu mulai.',
                ]);
            }

            if ($schedule->actual_end_at !== null) {
                if ($schedule->actual_start_at === null) {
                    throw ValidationException::withMessages([
                        'actual_start_at' =>
                            'Waktu mulai aktual wajib diisi sebelum waktu selesai aktual.',
                    ]);
                }

                if (
                    $schedule->actual_end_at->lessThanOrEqualTo(
                        $schedule->actual_start_at
                    )
                ) {
                    throw ValidationException::withMessages([
                        'actual_end_at' =>
                            'Waktu selesai aktual harus setelah waktu mulai aktual.',
                    ]);
                }
            }

            if (
                $schedule->exists &&
                $schedule->isDirty([
                    'group_match_id',
                    'knockout_match_id',
                ])
            ) {
                throw ValidationException::withMessages([
                    'group_match_id' =>
                        'Pertandingan pada jadwal yang sudah tersimpan tidak dapat diganti.',
                ]);
            }
        });
    }

    public function groupMatch(): BelongsTo
    {
        return $this->belongsTo(GroupMatch::class);
    }

    public function knockoutMatch(): BelongsTo
    {
        return $this->belongsTo(KnockoutMatch::class);
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }
}