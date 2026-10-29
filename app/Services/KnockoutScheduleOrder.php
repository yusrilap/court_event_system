<?php

namespace App\Services;

use App\Models\EventCategory;
use DomainException;

class KnockoutScheduleOrder
{
    public function __construct(
        private KnockoutBracketValidator $bracketValidator,
    ) {
    }

    public function build(EventCategory $category): array
    {
        $errors = $this->bracketValidator->errors($category);

        if (count($errors) > 0) {
            throw new DomainException(
                "Bagan kategori {$category->name} belum valid:\n- "
                . implode("\n- ", $errors)
            );
        }

        $matches = $category->knockoutMatches()
            ->with([
                'slots.sourceGroup.matches',
                'slots.sourceMatch',
            ])
            ->orderBy('id')
            ->get();

        $pending = [];

        foreach ($matches as $match) {
            $groupMatchIds = [];
            $knockoutMatchIds = [];

            foreach ($match->slots as $slot) {
                if ($slot->source_type === 'group_rank') {
                    $group = $slot->sourceGroup;

                    if (
                        $group === null ||
                        (int) $group->event_category_id !==
                            (int) $category->getKey()
                    ) {
                        throw new DomainException(
                            "Sumber grup pertandingan ID {$match->id} tidak valid."
                        );
                    }

                    if ($group->matches->isEmpty()) {
                        throw new DomainException(
                            "Pertandingan {$group->name} belum dibuat."
                        );
                    }

                    foreach ($group->matches as $groupMatch) {
                        $groupMatchIds[] = (int) $groupMatch->id;
                    }

                    continue;
                }

                if (! in_array(
                    $slot->source_type,
                    ['match_winner', 'match_loser'],
                    true
                )) {
                    throw new DomainException(
                        "Sumber slot pertandingan ID {$match->id} tidak dikenali."
                    );
                }

                $sourceMatch = $slot->sourceMatch;

                if (
                    $sourceMatch === null ||
                    (int) $sourceMatch->event_category_id !==
                        (int) $category->getKey()
                ) {
                    throw new DomainException(
                        "Pertandingan sumber untuk ID {$match->id} tidak valid."
                    );
                }

                $knockoutMatchIds[] = (int) $sourceMatch->id;
            }

            $groupMatchIds = array_values(array_unique($groupMatchIds));
            $knockoutMatchIds = array_values(array_unique($knockoutMatchIds));

            sort($groupMatchIds);
            sort($knockoutMatchIds);

            $pending[(int) $match->id] = [
                'knockout_match_id' => (int) $match->id,
                'round' => $match->round,
                'position' => (int) $match->position,
                'group_match_ids' => $groupMatchIds,
                'knockout_match_ids' => $knockoutMatchIds,
            ];
        }

        $ordered = [];
        $scheduledIds = [];

        while (count($pending) > 0) {
            $progress = false;

            foreach ($pending as $matchId => $item) {
                $unresolved = array_diff(
                    $item['knockout_match_ids'],
                    $scheduledIds
                );

                if (count($unresolved) > 0) {
                    continue;
                }

                $ordered[] = $item;
                $scheduledIds[] = $matchId;

                unset($pending[$matchId]);

                $progress = true;
            }

            if (! $progress) {
                throw new DomainException(
                    'Urutan pertandingan tidak dapat disusun. '
                    . 'Ada sumber pertandingan yang hilang atau jalur berputar.'
                );
            }
        }

        return $ordered;
    }
}