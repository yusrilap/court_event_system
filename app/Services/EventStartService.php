<?php

namespace App\Services;

use App\Models\CategoryScoringRule;
use App\Models\Event;
use App\Models\EventCategory;
use DomainException;
use Illuminate\Support\Facades\DB;

class EventStartService
{
    public function __construct(
        private EventScheduleSettings $settingsValidator,
        private GroupMatchReadiness $groupValidator,
        private KnockoutBracketValidator $bracketValidator,
        private MatchScoreValidator $scoreValidator,
    ) {
    }

    public function start(Event $event): Event
    {
        return DB::transaction(function () use ($event): Event {
            $lock = DB::table('schedule_locks')
                ->where('name', 'global')
                ->lockForUpdate()
                ->first();

            if ($lock === null) {
                throw new DomainException(
                    'Pengunci penjadwalan belum tersedia.'
                );
            }

            $event = Event::query()
                ->lockForUpdate()
                ->findOrFail($event->getKey());

            if ($event->status !== 'draft') {
                throw new DomainException(
                    'Hanya event berstatus draft yang dapat dimulai.'
                );
            }

            // Memeriksa tanggal, jam, court aktif, dan durasi.
            $this->settingsValidator->validate($event);

            $categories = EventCategory::query()
                ->where('event_id', $event->getKey())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($categories as $category) {
                $this->groupValidator->validate($category);

                $errors = $this->bracketValidator->errors($category);

                if (count($errors) > 0) {
                    throw new DomainException(
                        "Bagan kategori {$category->name} belum siap:\n- "
                        . implode("\n- ", $errors)
                    );
                }

                $groupMatches = $category->groupMatches()
                    ->with('schedule')
                    ->get();

                $knockoutMatches = $category->knockoutMatches()
                    ->with('schedule')
                    ->get();

                foreach (
                    $groupMatches->concat($knockoutMatches) as $match
                ) {
                    $schedule = $match->schedule;

                    if ($schedule === null) {
                        throw new DomainException(
                            "Kategori {$category->name} memiliki "
                            . 'pertandingan yang belum dijadwalkan.'
                        );
                    }

                    if (
                        $schedule->planned_start_at === null ||
                        $schedule->planned_end_at === null ||
                        $schedule->planned_end_at->lessThanOrEqualTo(
                            $schedule->planned_start_at
                        )
                    ) {
                        throw new DomainException(
                            "Waktu jadwal ID {$schedule->id} tidak valid."
                        );
                    }

                    if (! $event->courts()
                        ->whereKey($schedule->court_id)
                        ->exists()
                    ) {
                        throw new DomainException(
                            "Court jadwal ID {$schedule->id} "
                            . 'tidak terdaftar pada event.'
                        );
                    }

                    if (
                        $schedule->actual_start_at !== null ||
                        $schedule->actual_end_at !== null
                    ) {
                        throw new DomainException(
                            'Event masih draft tetapi sudah memiliki '
                            . 'waktu pelaksanaan aktual. Periksa data terlebih dahulu.'
                        );
                    }
                }

                $requiredPhases = array_values(array_unique([
                    'group',
                    ...$knockoutMatches->pluck('round')->all(),
                ]));

                $rules = $category->scoringRules()
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('phase');

                foreach ($requiredPhases as $phase) {
                    $phaseLabel = CategoryScoringRule::PHASE_LABELS[$phase]
                        ?? $phase;

                    $rule = $rules->get($phase);

                    if ($rule === null) {
                        throw new DomainException(
                            "Aturan skor {$phaseLabel} pada kategori "
                            . "{$category->name} belum diisi."
                        );
                    }

                    // Memeriksa konfigurasi memakai contoh skor valid.
                    // Tidak mencatat hasil pertandingan.
                    try {
                        $this->scoreValidator->validate(
                            $rule,
                            $rule->game_count,
                            0
                        );
                    } catch (DomainException $exception) {
                        throw new DomainException(
                            "Aturan {$phaseLabel} kategori {$category->name}: "
                            . $exception->getMessage()
                        );
                    }
                }
            }

            $event->status = 'ongoing';
            $event->save();

            return $event;
        }, 3);
    }
}