<?php

namespace App\Services;

use App\Models\EventCategory;
use DomainException;

class GroupMatchReadiness
{
    public function validate(EventCategory $category): void
    {
        $category = EventCategory::query()
            ->with([
                'pairs',
                'groups.pairs',
                'groups.matches',
            ])
            ->findOrFail($category->getKey());

        $pairCount = (int) $category->pair_count;
        $groupCount = (int) $category->group_count;

        if (
            ! in_array($pairCount, [8, 16], true) ||
            ! in_array($groupCount, [1, 2, 4, 8], true) ||
            $pairCount % $groupCount !== 0 ||
            $pairCount / $groupCount < 2
        ) {
            $this->reject($category, 'Format jumlah pasangan dan grup tidak valid.');
        }

        if ($category->groups->count() !== $groupCount) {
            $this->reject($category, 'Jumlah grup belum sesuai pengaturan.');
        }

        if ($category->pairs->count() !== $pairCount) {
            $this->reject($category, 'Jumlah pasangan belum sesuai pengaturan.');
        }

        $playerIds = $category->pairs
            ->flatMap(fn ($pair) => [
                $pair->player_one_id,
                $pair->player_two_id,
            ]);

        if (
            $playerIds->contains(null) ||
            $playerIds->unique()->count() !== $pairCount * 2
        ) {
            $this->reject(
                $category,
                'Identitas pemain tidak lengkap atau ada pemain terdaftar lebih dari sekali.'
            );
        }

        $groupIds = $category->groups
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($category->pairs as $pair) {
            if (
                $pair->tournament_group_id === null ||
                ! in_array(
                    (int) $pair->tournament_group_id,
                    $groupIds,
                    true
                )
            ) {
                $this->reject(
                    $category,
                    "Pasangan ID {$pair->id} belum ditempatkan pada grup yang sesuai."
                );
            }
        }

        $capacity = intdiv($pairCount, $groupCount);

        foreach ($category->groups as $group) {
            if ($group->pairs->count() !== $capacity) {
                $this->reject(
                    $category,
                    "{$group->name} harus berisi {$capacity} pasangan."
                );
            }

            foreach ($group->pairs as $pair) {
                if (
                    (int) $pair->event_category_id !==
                    (int) $category->getKey()
                ) {
                    $this->reject(
                        $category,
                        "{$group->name} berisi pasangan dari kategori lain."
                    );
                }
            }

            $pairIds = $group->pairs
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values()
                ->all();

            $expected = [];

            for ($i = 0; $i < count($pairIds); $i++) {
                for ($j = $i + 1; $j < count($pairIds); $j++) {
                    $expected[] = $this->fixtureKey(
                        $pairIds[$i],
                        $pairIds[$j]
                    );
                }
            }

            $actual = [];

            foreach ($group->matches as $match) {
                if ($match->status !== 'pending') {
                    $this->reject(
                        $category,
                        "{$group->name} memiliki pertandingan yang tidak lagi pending."
                    );
                }

                $actual[] = $this->fixtureKey(
                    (int) $match->pair_one_id,
                    (int) $match->pair_two_id
                );
            }

            sort($expected);
            sort($actual);

            if ($actual !== $expected) {
                $this->reject(
                    $category,
                    "Pertandingan {$group->name} belum lengkap, berulang, atau memuat pasangan yang tidak sesuai."
                );
            }
        }
    }

    private function fixtureKey(int $first, int $second): string
    {
        return min($first, $second) . ':' . max($first, $second);
    }

    private function reject(
        EventCategory $category,
        string $message
    ): never {
        throw new DomainException(
            "Kategori {$category->name}: {$message}"
        );
    }
}