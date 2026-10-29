<?php

namespace App\Services;

use App\Models\KnockoutMatch;
use DomainException;

class KnockoutPotentialPlayers
{
    public function resolve(KnockoutMatch $match): array
    {
        $visiting = [];
        $resolved = [];

        return $this->collect(
            $match,
            (int) $match->event_category_id,
            $visiting,
            $resolved
        );
    }

    private function collect(
        KnockoutMatch $match,
        int $categoryId,
        array &$visiting,
        array &$resolved,
    ): array {
        $matchId = (int) $match->getKey();

        if (isset($visiting[$matchId])) {
            throw new DomainException(
                'Jalur bagan gugur membentuk putaran. Periksa sumber slot pertandingan.'
            );
        }

        if (isset($resolved[$matchId])) {
            return $resolved[$matchId];
        }

        $match = KnockoutMatch::query()
            ->with([
                'slots.sourceGroup.pairs',
                'slots.sourceMatch',
            ])
            ->findOrFail($matchId);

        if ((int) $match->event_category_id !== $categoryId) {
            throw new DomainException(
                'Sumber pertandingan gugur harus berasal dari kategori yang sama.'
            );
        }

        $sides = $match->slots
            ->pluck('side')
            ->sort()
            ->values()
            ->all();

        if ($sides !== ['A', 'B']) {
            throw new DomainException(
                "Slot A dan B pertandingan gugur ID {$matchId} belum lengkap."
            );
        }

        $visiting[$matchId] = true;
        $playerIds = [];

        foreach ($match->slots as $slot) {
            if ($slot->source_type === 'group_rank') {
                $group = $slot->sourceGroup;

                if (
                    $group === null ||
                    (int) $group->event_category_id !== $categoryId
                ) {
                    throw new DomainException(
                        "Sumber grup pertandingan ID {$matchId} tidak valid."
                    );
                }

                if ($group->pairs->isEmpty()) {
                    throw new DomainException(
                        "Grup {$group->name} belum memiliki pasangan."
                    );
                }

                foreach ($group->pairs as $pair) {
                    if (
                        (int) $pair->event_category_id !== $categoryId ||
                        $pair->player_one_id === null ||
                        $pair->player_two_id === null
                    ) {
                        throw new DomainException(
                            "Data pasangan dalam grup {$group->name} tidak valid."
                        );
                    }

                    $playerIds[] = (int) $pair->player_one_id;
                    $playerIds[] = (int) $pair->player_two_id;
                }

                continue;
            }

            if (! in_array(
                $slot->source_type,
                ['match_winner', 'match_loser'],
                true
            )) {
                throw new DomainException(
                    "Jenis sumber slot pertandingan ID {$matchId} tidak dikenali."
                );
            }

            $sourceMatch = $slot->sourceMatch;

            if ($sourceMatch === null) {
                throw new DomainException(
                    "Pertandingan sumber untuk ID {$matchId} tidak ditemukan."
                );
            }

            $sourcePlayers = $this->collect(
                $sourceMatch,
                $categoryId,
                $visiting,
                $resolved
            );

            $playerIds = array_merge($playerIds, $sourcePlayers);
        }

        unset($visiting[$matchId]);

        $playerIds = array_values(array_unique($playerIds));
        sort($playerIds);

        $resolved[$matchId] = $playerIds;

        return $playerIds;
    }
}