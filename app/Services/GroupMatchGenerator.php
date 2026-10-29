<?php

namespace App\Services;

use App\Models\EventCategory;
use App\Models\GroupMatch;
use App\Models\TournamentPair;
use DomainException;
use Illuminate\Support\Facades\DB;

class GroupMatchGenerator
{
    public function generate(EventCategory $category): int
    {
        return DB::transaction(function () use ($category): int {
            $category = EventCategory::query()
                ->lockForUpdate()
                ->findOrFail($category->getKey());

            if ($category->event->status !== 'draft') {
                throw new DomainException(
                    'Pertandingan hanya dapat dibuat saat event berstatus Draft.'
                );
            }

            $pairCount = (int) $category->pair_count;
            $groupCount = (int) $category->group_count;

            if (
                ! in_array($pairCount, [8, 16], true) ||
                ! in_array($groupCount, [1, 2, 4, 8], true)
            ) {
                throw new DomainException(
                    'Pengaturan jumlah pasangan dan grup belum valid.'
                );
            }

            if (
                $pairCount % $groupCount !== 0 ||
                intdiv($pairCount, $groupCount) < 2
            ) {
                throw new DomainException(
                    'Pembagian harus merata dengan minimal 2 pasangan per grup.'
                );
            }

            $groups = $category->groups()
                ->orderBy('sort_order')
                ->lockForUpdate()
                ->get();

            if ($groups->count() !== $groupCount) {
                throw new DomainException(
                    'Jumlah grup yang tersedia tidak sesuai pengaturan kategori.'
                );
            }

            $groupIds = $groups->modelKeys();

            // Sertakan pasangan yang menunjuk grup kategori ini,
            // agar penempatan lintas kategori juga dapat terdeteksi.
            $pairs = TournamentPair::query()
                ->where(function ($query) use ($category, $groupIds): void {
                    $query->where('event_category_id', $category->id)
                        ->orWhereIn('tournament_group_id', $groupIds);
                })
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($pairs as $pair) {
                if ((int) $pair->event_category_id !== (int) $category->id) {
                    throw new DomainException(
                        'Ada pasangan dari kategori lain dalam grup ini.'
                    );
                }

                if (
                    $pair->tournament_group_id === null ||
                    ! in_array($pair->tournament_group_id, $groupIds, true)
                ) {
                    throw new DomainException(
                        'Semua pasangan harus ditempatkan ke grup kategori ini.'
                    );
                }
            }

            if ($pairs->count() !== $pairCount) {
                throw new DomainException(
                    "Kategori harus memiliki tepat {$pairCount} pasangan. "
                    . "Saat ini terdapat {$pairs->count()} pasangan."
                );
            }

            $playerIds = $pairs->flatMap(
                fn (TournamentPair $pair): array => [
                    $pair->player_one_id,
                    $pair->player_two_id,
                ]
            );

            if ($playerIds->unique()->count() !== $pairCount * 2) {
                throw new DomainException(
                    'Ada pemain yang terdaftar lebih dari sekali dalam kategori ini.'
                );
            }

            $capacity = intdiv($pairCount, $groupCount);

            foreach ($groups as $group) {
                $occupied = $pairs
                    ->where('tournament_group_id', $group->id)
                    ->count();

                if ($occupied !== $capacity) {
                    throw new DomainException(
                        "{$group->name} harus berisi {$capacity} pasangan. "
                        . "Saat ini terdapat {$occupied} pasangan."
                    );
                }
            }

            $existingMatches = GroupMatch::query()
                ->whereIn('tournament_group_id', $groupIds)
                ->lockForUpdate()
                ->get();

            if ($existingMatches->isNotEmpty()) {
                throw new DomainException(
                    'Pertandingan grup sudah ada. Generate ulang tidak diizinkan.'
                );
            }

            $created = 0;

            foreach ($groups as $group) {
                $pairIds = $pairs
                    ->where('tournament_group_id', $group->id)
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->values()
                    ->all();

                foreach ($this->buildRoundRobin($pairIds) as $fixture) {
                    $group->matches()->create($fixture);
                    $created++;
                }
            }

            return $created;
        });
    }

    private function buildRoundRobin(array $pairIds): array
    {
        $count = count($pairIds);

        // Format awal 8/16 pasangan dengan 1/2/4/8 grup
        // menghasilkan jumlah pasangan per grup yang genap.
        if ($count < 2 || $count % 2 !== 0) {
            throw new DomainException(
                'Generator saat ini memerlukan jumlah pasangan per grup yang genap.'
            );
        }

        $fixtures = [];
        $rotation = array_values($pairIds);

        for ($round = 1; $round < $count; $round++) {
            for ($index = 0; $index < intdiv($count, 2); $index++) {
                $first = $rotation[$index];
                $second = $rotation[$count - 1 - $index];

                $fixtures[] = [
                    'pair_one_id' => min($first, $second),
                    'pair_two_id' => max($first, $second),
                    'round_number' => $round,
                ];
            }

            // Pasangan pertama tetap, pasangan lainnya berputar.
            $last = array_pop($rotation);
            array_splice($rotation, 1, 0, [$last]);
        }

        return $fixtures;
    }
}