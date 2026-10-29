<?php

namespace App\Services;

use App\Models\Event;
use App\Models\MatchSchedule;
use DomainException;

class ExistingScheduleBookings
{
    public function __construct(
        private EventScheduleSettings $settingsValidator,
        private KnockoutPotentialPlayers $potentialPlayers,
    ) {
    }

    public function load(Event $event): array
    {
        $settings = $this->settingsValidator->validate($event);

        $courtIds = array_map('intval', $settings['court_ids']);

        $playerIds = $event->categories()
            ->with('pairs')
            ->get()
            ->flatMap(fn ($category) => $category->pairs)
            ->flatMap(fn ($pair) => [
                (int) $pair->player_one_id,
                (int) $pair->player_two_id,
            ])
            ->unique()
            ->values()
            ->all();

        $schedules = MatchSchedule::query()
            ->with([
                'groupMatch.pairOne',
                'groupMatch.pairTwo',
                'groupMatch.group.category.event',
                'knockoutMatch.category.event',
            ])
            ->orderBy('id')
            ->get();

        $bookings = [];

        foreach ($schedules as $schedule) {
            $hasGroup = $schedule->group_match_id !== null;
            $hasKnockout = $schedule->knockout_match_id !== null;

            if ($hasGroup === $hasKnockout) {
                throw new DomainException(
                    "Jadwal ID {$schedule->id} memiliki referensi pertandingan tidak valid."
                );
            }

            if ($hasGroup) {
                $match = $schedule->groupMatch;

                if (
                    $match === null ||
                    $match->pairOne === null ||
                    $match->pairTwo === null
                ) {
                    throw new DomainException(
                        "Data pertandingan jadwal ID {$schedule->id} tidak lengkap."
                    );
                }

                $bookingPlayerIds = [
                    (int) $match->pairOne->player_one_id,
                    (int) $match->pairOne->player_two_id,
                    (int) $match->pairTwo->player_one_id,
                    (int) $match->pairTwo->player_two_id,
                ];

                $sourceEvent = $match->group?->category?->event;
            } else {
                $match = $schedule->knockoutMatch;

                if ($match === null) {
                    throw new DomainException(
                        "Pertandingan gugur jadwal ID {$schedule->id} tidak ditemukan."
                    );
                }

                $bookingPlayerIds = $this->potentialPlayers->resolve($match);
                $sourceEvent = $match->category?->event;
            }

            $usesSameCourt = in_array(
                (int) $schedule->court_id,
                $courtIds,
                true
            );

            $sharesPlayers = count(array_intersect(
                $playerIds,
                $bookingPlayerIds
            )) > 0;

            if (! $usesSameCourt && ! $sharesPlayers) {
                continue;
            }

            if (
                $sourceEvent === null ||
                $sourceEvent->court_gap_minutes === null ||
                $sourceEvent->player_rest_minutes === null
            ) {
                throw new DomainException(
                    "Pengaturan jeda event asal jadwal ID {$schedule->id} tidak lengkap."
                );
            }

            $courtGap = (int) $sourceEvent->court_gap_minutes;
            $playerRest = (int) $sourceEvent->player_rest_minutes;

            if (
                $courtGap < 0 || $courtGap > 1440 ||
                $playerRest < 0 || $playerRest > 1440
            ) {
                throw new DomainException(
                    "Pengaturan jeda jadwal ID {$schedule->id} tidak valid."
                );
            }

            if (
                $schedule->actual_start_at !== null &&
                $schedule->actual_end_at === null
            ) {
                throw new DomainException(
                    "Pertandingan pada jadwal ID {$schedule->id} "
                    . 'sedang berjalan dan waktu selesainya belum diketahui. '
                    . 'Selesaikan pencatatan pertandingan sebelum generate jadwal terkait.'
                );
            }

            if (
                $schedule->actual_end_at !== null &&
                $schedule->actual_start_at === null
            ) {
                throw new DomainException(
                    "Waktu aktual jadwal ID {$schedule->id} tidak lengkap."
                );
            }

            $start = $schedule->actual_start_at
                ?? $schedule->planned_start_at;

            $end = $schedule->actual_end_at
                ?? $schedule->planned_end_at;

            if (
                $start === null ||
                $end === null ||
                $end->lessThanOrEqualTo($start)
            ) {
                throw new DomainException(
                    "Urutan waktu jadwal ID {$schedule->id} tidak valid."
                );
            }

            $bookings[] = [
                'court_id' => (int) $schedule->court_id,
                'player_ids' => $bookingPlayerIds,
                'start_at' => $start->setTimezone('UTC'),
                'end_at' => $end->setTimezone('UTC'),
                'court_gap_minutes' => $courtGap,
                'player_rest_minutes' => $playerRest,
            ];
        }

        return $bookings;
    }
}