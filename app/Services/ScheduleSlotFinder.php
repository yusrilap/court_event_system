<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

class ScheduleSlotFinder
{
    /**
     * Setiap booking berisi:
     * court_id, player_ids, start_at, end_at,
     * court_gap_minutes, player_rest_minutes.
     *
     * start_at dan end_at wajib CarbonImmutable dalam UTC.
     */
    public function find(
        CarbonImmutable $earliestStart,
        int $durationMinutes,
        array $courtIds,
        array $playerIds,
        array $bookings,
        int $courtGapMinutes,
        int $playerRestMinutes,
    ): array {
        if (
            $durationMinutes < 1 ||
            $courtGapMinutes < 0 ||
            $playerRestMinutes < 0 ||
            empty($courtIds) ||
            empty($playerIds)
        ) {
            throw new InvalidArgumentException(
                'Durasi, court, pemain, atau pengaturan jeda tidak valid.'
            );
        }

        $courtIds = array_values(array_unique(
            array_map('intval', $courtIds)
        ));

        $playerIds = array_values(array_unique(
            array_map('intval', $playerIds)
        ));

        sort($courtIds);

        $bestSlot = null;

        foreach ($courtIds as $courtId) {
            $start = $earliestStart->setTimezone('UTC');

            while (true) {
                $end = $start->addMinutes($durationMinutes);
                $nextStart = $start;

                foreach ($bookings as $booking) {
                    $sameCourt = (int) $booking['court_id'] === $courtId;

                    $sharedPlayers = count(array_intersect(
                        $playerIds,
                        $booking['player_ids']
                    )) > 0;

                    if (! $sameCourt && ! $sharedPlayers) {
                        continue;
                    }

                    $requiredGap = 0;

                    if ($sameCourt) {
                        $requiredGap = max(
                            $requiredGap,
                            $courtGapMinutes,
                            (int) $booking['court_gap_minutes']
                        );
                    }

                    if ($sharedPlayers) {
                        $requiredGap = max(
                            $requiredGap,
                            $playerRestMinutes,
                            (int) $booking['player_rest_minutes']
                        );
                    }

                    $blockedStart = $booking['start_at']
                        ->subMinutes($requiredGap);

                    $blockedEnd = $booking['end_at']
                        ->addMinutes($requiredGap);

                    $overlaps = $start->lessThan($blockedEnd)
                        && $end->greaterThan($blockedStart);

                    if (
                        $overlaps &&
                        $blockedEnd->greaterThan($nextStart)
                    ) {
                        $nextStart = $blockedEnd;
                    }
                }

                if ($nextStart->equalTo($start)) {
                    break;
                }

                // Periksa ulang seluruh booking pada waktu yang baru.
                $start = $nextStart;
            }

            if (
                $bestSlot === null ||
                $start->lessThan($bestSlot['planned_start_at'])
            ) {
                $bestSlot = [
                    'court_id' => $courtId,
                    'planned_start_at' => $start,
                    'planned_end_at' => $start
                        ->addMinutes($durationMinutes),
                ];
            }
        }

        return $bestSlot;
    }
}