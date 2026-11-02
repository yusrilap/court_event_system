<?php

namespace App\Services;

use App\Models\CategoryScoringRule;
use App\Models\TournamentGroup;
use DomainException;

class GroupStandingsCalculator
{
    public function __construct(
        private MatchScoreValidator $scoreValidator,
    ) {
    }

    public function calculate(TournamentGroup $group): array
    {
        $group = TournamentGroup::query()
            ->with([
                'pairs.playerOne',
                'pairs.playerTwo',
                'matches',
            ])
            ->findOrFail($group->getKey());

        $rows = [];

        foreach ($group->pairs->sortBy('id') as $pair) {
            if (
                (int) $pair->event_category_id !==
                (int) $group->event_category_id
            ) {
                throw new DomainException(
                    'Grup memiliki pasangan dari kategori lain.'
                );
            }

            $rows[$pair->id] = [
                'pair_id' => (int) $pair->id,
                'pair_name' =>
                    ($pair->playerOne?->name ?? 'Pemain tidak ditemukan')
                    . ' / '
                    . ($pair->playerTwo?->name ?? 'Pemain tidak ditemukan'),
                'played' => 0,
                'win' => 0,
                'lose' => 0,
                'games_for' => 0,
                'games_against' => 0,
                'diff' => 0,
            ];
        }

        $seenFixtures = [];

        foreach ($group->matches as $match) {
            // Pertandingan yang belum selesai tidak dihitung.
            if ($match->status !== 'completed') {
                continue;
            }

            $one = (int) $match->pair_one_id;
            $two = (int) $match->pair_two_id;

            if (
                $one === $two ||
                ! isset($rows[$one], $rows[$two])
            ) {
                throw new DomainException(
                    "Pasangan pada pertandingan ID {$match->id} "
                    . 'tidak sesuai dengan anggota grup.'
                );
            }

            $fixtureKey = min($one, $two) . ':' . max($one, $two);

            if (isset($seenFixtures[$fixtureKey])) {
                throw new DomainException(
                    'Ada hasil pertandingan berulang untuk dua pasangan yang sama.'
                );
            }

            $seenFixtures[$fixtureKey] = true;

            if (
                $match->score_one === null ||
                $match->score_two === null ||
                $match->result_scoring_format === null ||
                $match->result_game_count === null ||
                $match->result_recorded_at === null ||
                $match->result_recorded_by === null
            ) {
                throw new DomainException(
                    "Data hasil pertandingan ID {$match->id} belum lengkap."
                );
            }

            // Gunakan salinan aturan saat hasil dicatat,
            // bukan konfigurasi aturan yang dibaca ulang.
            $rule = new CategoryScoringRule([
                'phase' => 'group',
                'format' => $match->result_scoring_format,
                'game_count' => $match->result_game_count,
            ]);

            try {
                $result = $this->scoreValidator->validate(
                    $rule,
                    $match->score_one,
                    $match->score_two
                );
            } catch (DomainException $exception) {
                throw new DomainException(
                    "Hasil pertandingan ID {$match->id} tidak valid: "
                    . $exception->getMessage()
                );
            }

            $scoreOne = $result['score_a'];
            $scoreTwo = $result['score_b'];

            $rows[$one]['played']++;
            $rows[$two]['played']++;

            $rows[$one]['games_for'] += $scoreOne;
            $rows[$one]['games_against'] += $scoreTwo;

            $rows[$two]['games_for'] += $scoreTwo;
            $rows[$two]['games_against'] += $scoreOne;

            if ($result['winner_side'] === 'A') {
                $rows[$one]['win']++;
                $rows[$two]['lose']++;
            } else {
                $rows[$two]['win']++;
                $rows[$one]['lose']++;
            }
        }

        foreach ($rows as $pairId => $row) {
            $rows[$pairId]['diff'] =
                $row['games_for'] - $row['games_against'];
        }

        // Urutan ID pasangan, belum merupakan peringkat klasemen.
        return array_values($rows);
    }
}