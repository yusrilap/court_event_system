<?php

namespace App\Services;

use App\Models\EventCategory;

class KnockoutBracketValidator
{
    public function errors(EventCategory $category): array
    {
        $category = EventCategory::findOrFail($category->getKey());

        $pairCount = (int) $category->pair_count;
        $groupCount = (int) $category->group_count;
        $qualifiers = (int) $category->qualifiers_per_group;

        if (
            ! in_array($pairCount, [8, 16], true) ||
            ! in_array($groupCount, [1, 2, 4, 8], true) ||
            ! in_array($qualifiers, [1, 2], true)
        ) {
            return ['Pengaturan format kategori belum valid.'];
        }

        if (
            $pairCount % $groupCount !== 0 ||
            intdiv($pairCount, $groupCount) < 2
        ) {
            return ['Pembagian grup harus merata, minimal 2 pasangan per grup.'];
        }

        $qualified = $groupCount * $qualifiers;

        if (
            ! in_array($qualified, [2, 4, 8, 16], true) ||
            $qualified > $pairCount
        ) {
            return ['Jumlah pasangan lolos tidak sesuai format fase gugur.'];
        }

        if ($category->has_third_place_match && $qualified < 4) {
            return ['Perebutan juara 3 memerlukan fase semifinal.'];
        }

        $groups = $category->groups()
            ->orderBy('sort_order')
            ->get();

        if ($groups->count() !== $groupCount) {
            return ['Jumlah grup tersedia tidak sesuai pengaturan kategori.'];
        }

        $matches = $category->knockoutMatches()
            ->with('slots')
            ->get();

        $roundNames = [
            16 => 'round_of_16',
            8 => 'quarterfinal',
            4 => 'semifinal',
            2 => 'final',
        ];

        $roundLabels = [
            'round_of_16' => 'Babak 16 Besar',
            'quarterfinal' => 'Perempat Final',
            'semifinal' => 'Semifinal',
            'final' => 'Final',
            'third_place' => 'Perebutan Juara 3',
        ];

        $expectedRounds = [];

        for ($participants = $qualified; $participants >= 2; $participants = intdiv($participants, 2)) {
            $expectedRounds[$roundNames[$participants]] = intdiv(
                $participants,
                2
            );
        }

        if ($category->has_third_place_match) {
            $expectedRounds['third_place'] = 1;
        }

        $errors = [];

        foreach ($expectedRounds as $round => $count) {
            $positions = $matches
                ->where('round', $round)
                ->pluck('position')
                ->map(fn ($position): int => (int) $position)
                ->sort()
                ->values()
                ->all();

            if ($positions !== range(1, $count)) {
                $errors[] = "{$roundLabels[$round]} harus memiliki "
                    . "{$count} pertandingan dengan nomor berurutan.";
            }
        }

        foreach ($matches->pluck('round')->unique() as $round) {
            if (! array_key_exists($round, $expectedRounds)) {
                $errors[] = 'Terdapat babak yang tidak sesuai format kategori: '
                    . ($roundLabels[$round] ?? $round) . '.';
            }
        }

        if ($errors !== []) {
            return $errors;
        }

        // Setiap sumber berikut harus digunakan tepat satu kali.
        $expectedSources = [];

        foreach ($groups as $group) {
            for ($rank = 1; $rank <= $qualifiers; $rank++) {
                $rankLabel = $rank === 1 ? 'Juara' : 'Runner-up';

                $expectedSources["group:{$group->id}:{$rank}"] =
                    "{$rankLabel} {$group->name}";
            }
        }

        foreach ($matches as $match) {
            $label = "{$roundLabels[$match->round]} {$match->position}";

            if (! in_array($match->round, ['final', 'third_place'], true)) {
                $expectedSources["match_winner:{$match->id}"] =
                    "Pemenang {$label}";
            }

            if (
                $category->has_third_place_match &&
                $match->round === 'semifinal'
            ) {
                $expectedSources["match_loser:{$match->id}"] =
                    "Yang kalah di {$label}";
            }
        }

        $service = app(KnockoutSlotService::class);
        $usage = [];

        foreach ($matches as $match) {
            $label = "{$roundLabels[$match->round]} {$match->position}";

            $sides = $match->slots
                ->pluck('side')
                ->sort()
                ->values()
                ->all();

            if ($sides !== ['A', 'B']) {
                $errors[] = "{$label}: slot A dan B belum lengkap.";
            }

            $allowedSources = $service->availableSources($match);

            foreach ($match->slots as $slot) {
                $key = $service->sourceKey($slot);

                $usage[$key] = ($usage[$key] ?? 0) + 1;

                $expected = $allowedSources[$key] ?? null;

                if (! $expected) {
                    $errors[] = "{$label}, slot {$slot->side}: "
                        . 'sumber tidak sesuai jalur atau digunakan berulang.';

                    continue;
                }

                foreach ([
                    'source_type',
                    'source_group_id',
                    'source_rank',
                    'source_match_id',
                ] as $field) {
                    if ($slot->{$field} !== $expected[$field]) {
                        $errors[] = "{$label}, slot {$slot->side}: "
                            . 'isian sumber tidak konsisten.';

                        break;
                    }
                }
            }
        }

        foreach ($expectedSources as $key => $label) {
            $count = $usage[$key] ?? 0;

            if ($count === 0) {
                $errors[] = "{$label} belum digunakan pada slot bagan.";
            } elseif ($count > 1) {
                $errors[] = "{$label} digunakan lebih dari satu kali.";
            }
        }

        foreach ($usage as $key => $count) {
            if (! array_key_exists($key, $expectedSources)) {
                $errors[] = "Ada sumber peserta yang tidak diharapkan: {$key}.";
            }
        }

        return array_values(array_unique($errors));
    }
}