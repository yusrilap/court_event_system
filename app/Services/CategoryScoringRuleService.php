<?php

namespace App\Services;

use App\Models\CategoryScoringRule;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\MatchSchedule;
use DomainException;
use Illuminate\Support\Facades\DB;

class CategoryScoringRuleService
{
    public function save(
        EventCategory $category,
        string $phase,
        string $format,
        mixed $gameCount,
    ): CategoryScoringRule {
        if (! array_key_exists($phase, CategoryScoringRule::PHASE_LABELS)) {
            throw new DomainException('Fase pertandingan tidak dikenali.');
        }

        if (! array_key_exists($format, CategoryScoringRule::FORMAT_LABELS)) {
            throw new DomainException('Format skor tidak dikenali.');
        }

        if (
            ! is_int($gameCount) &&
            ! (
                is_string($gameCount) &&
                preg_match('/^[0-9]{1,5}$/D', $gameCount) === 1
            )
        ) {
            throw new DomainException(
                'Jumlah game harus berupa bilangan bulat.'
            );
        }

        $gameCount = (int) $gameCount;

        if ($gameCount < 1 || $gameCount > 65535) {
            throw new DomainException(
                'Jumlah game harus antara 1 dan 65535.'
            );
        }

        return DB::transaction(function () use (
            $category,
            $phase,
            $format,
            $gameCount
        ): CategoryScoringRule {
            $lock = DB::table('schedule_locks')
                ->where('name', 'global')
                ->lockForUpdate()
                ->first();

            if ($lock === null) {
                throw new DomainException(
                    'Pengunci penjadwalan belum tersedia.'
                );
            }

            $category = EventCategory::query()
                ->lockForUpdate()
                ->findOrFail($category->getKey());

            $event = Event::query()
                ->lockForUpdate()
                ->findOrFail($category->event_id);

            if (! in_array($event->status, ['draft', 'ongoing'], true)) {
                throw new DomainException(
                    'Aturan skor pada event yang sudah selesai tidak dapat diubah.'
                );
            }

            // Fase gugur harus tersedia dalam struktur kategori.
            if (
                $phase !== 'group' &&
                ! $category->knockoutMatches()
                    ->where('round', $phase)
                    ->exists()
            ) {
                throw new DomainException(
                    'Fase tersebut belum tersedia dalam bagan kategori.'
                );
            }

            $rule = $category->scoringRules()
                ->where('phase', $phase)
                ->lockForUpdate()
                ->first();

            if ($rule?->isLocked()) {
                throw new DomainException(
                    'Aturan skor fase ini sudah dikunci.'
                );
            }

            if ($this->phaseHasStarted($category, $phase)) {
                throw new DomainException(
                    'Aturan skor tidak dapat diubah karena fase ini '
                    . 'sudah mulai dimainkan atau memiliki hasil.'
                );
            }

            if ($rule === null) {
                $rule = new CategoryScoringRule();
                $rule->category()->associate($category);
                $rule->phase = $phase;
            }

            $rule->format = $format;
            $rule->game_count = $gameCount;
            $rule->save();

            return $rule;
        }, 3);
    }

    private function phaseHasStarted(
        EventCategory $category,
        string $phase
    ): bool {
        if (
            $phase === 'group' &&
            $category->groupMatches()
                ->where('group_matches.status', 'completed')
                ->exists()
        ) {
            return true;
        }

        $query = MatchSchedule::query()
            ->where(function ($query): void {
                $query
                    ->whereNotNull('actual_start_at')
                    ->orWhereNotNull('actual_end_at');
            });

        if ($phase === 'group') {
            $query->whereHas(
                'groupMatch.group',
                fn ($group) => $group->where(
                    'event_category_id',
                    $category->getKey()
                )
            );
        } else {
            $query->whereHas(
                'knockoutMatch',
                fn ($match) => $match
                    ->where('event_category_id', $category->getKey())
                    ->where('round', $phase)
            );
        }

        return $query->exists();
    }
}