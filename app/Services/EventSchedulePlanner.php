<?php

namespace App\Services;

use App\Models\Event;
use App\Models\KnockoutMatch;
use Carbon\CarbonImmutable;
use DomainException;

class EventSchedulePlanner
{
    public function __construct(
        private EventScheduleReadiness $readiness,
        private ScheduleSlotFinder $slotFinder,
        private KnockoutScheduleOrder $knockoutOrder,
        private KnockoutPotentialPlayers $potentialPlayers,
    ) {
    }

    /**
     * $existingBookings berisi jadwal lain yang harus dihindari.
     * Formatnya mengikuti ScheduleSlotFinder.
     *
     * Method ini hanya menyusun rancangan, tidak menyimpan data.
     */
    public function plan(Event $event, array $existingBookings): array
    {
        $settings = $this->readiness->validate($event);

        $categories = $event->categories()
            ->orderBy('id')
            ->get();

        $bookings = $existingBookings;
        $rows = [];
        $finishTimes = [];

        $allocate = function (
            string $type,
            int $matchId,
            int $categoryId,
            array $playerIds,
            CarbonImmutable $earliestStart,
        ) use (
            $settings,
            &$bookings,
            &$rows,
            &$finishTimes,
        ): void {
            $slot = $this->slotFinder->find(
                earliestStart: $earliestStart,
                durationMinutes:
                    $settings['category_duration_minutes'][$categoryId],
                courtIds: $settings['court_ids'],
                playerIds: $playerIds,
                bookings: $bookings,
                courtGapMinutes: $settings['court_gap_minutes'],
                playerRestMinutes: $settings['player_rest_minutes'],
            );

            $rows[] = [
                'event_category_id' => $categoryId,
                'group_match_id' => $type === 'group' ? $matchId : null,
                'knockout_match_id' => $type === 'knockout' ? $matchId : null,
                'court_id' => $slot['court_id'],
                'planned_start_at' => $slot['planned_start_at'],
                'planned_end_at' => $slot['planned_end_at'],
            ];

            $bookings[] = [
                'court_id' => $slot['court_id'],
                'player_ids' => $playerIds,
                'start_at' => $slot['planned_start_at'],
                'end_at' => $slot['planned_end_at'],
                'court_gap_minutes' => $settings['court_gap_minutes'],
                'player_rest_minutes' => $settings['player_rest_minutes'],
            ];

            $finishTimes["{$type}:{$matchId}"] = $slot['planned_end_at'];
        };

        // Susun seluruh pertandingan grup terlebih dahulu.
        foreach ($categories as $category) {
            $matches = $category->groupMatches()
                ->with(['pairOne', 'pairTwo'])
                ->orderBy('group_matches.round_number')
                ->orderBy('group_matches.id')
                ->get();

            foreach ($matches as $match) {
                if ($match->pairOne === null || $match->pairTwo === null) {
                    throw new DomainException(
                        "Pasangan pertandingan grup ID {$match->id} tidak lengkap."
                    );
                }

                $playerIds = [
                    (int) $match->pairOne->player_one_id,
                    (int) $match->pairOne->player_two_id,
                    (int) $match->pairTwo->player_one_id,
                    (int) $match->pairTwo->player_two_id,
                ];

                $allocate(
                    'group',
                    (int) $match->id,
                    (int) $category->id,
                    $playerIds,
                    $settings['start_at_utc'],
                );
            }
        }

        // Susun fase gugur mengikuti ketergantungan bagan.
        foreach ($categories as $category) {
            $order = $this->knockoutOrder->build($category);

            foreach ($order as $item) {
                $earliestStart = $settings['start_at_utc'];

                $dependencyKeys = array_merge(
                    array_map(
                        fn ($id) => "group:{$id}",
                        $item['group_match_ids']
                    ),
                    array_map(
                        fn ($id) => "knockout:{$id}",
                        $item['knockout_match_ids']
                    ),
                );

                foreach ($dependencyKeys as $key) {
                    if (! isset($finishTimes[$key])) {
                        throw new DomainException(
                            "Jadwal pertandingan sumber {$key} belum tersedia."
                        );
                    }

                    if ($finishTimes[$key]->greaterThan($earliestStart)) {
                        $earliestStart = $finishTimes[$key];
                    }
                }

                $match = KnockoutMatch::query()
                    ->where('event_category_id', $category->id)
                    ->findOrFail($item['knockout_match_id']);

                $allocate(
                    'knockout',
                    (int) $match->id,
                    (int) $category->id,
                    $this->potentialPlayers->resolve($match),
                    $earliestStart,
                );
            }
        }

        return collect($rows)
            ->sort(function (array $first, array $second): int {
                return (
                    $first['planned_start_at']->getTimestamp()
                    <=>
                    $second['planned_start_at']->getTimestamp()
                ) ?: ($first['court_id'] <=> $second['court_id']);
            })
            ->values()
            ->all();
    }
}