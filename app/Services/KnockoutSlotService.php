<?php

namespace App\Services;

use App\Models\EventCategory;
use App\Models\KnockoutMatch;
use App\Models\KnockoutSlot;
use DomainException;
use Illuminate\Support\Facades\DB;
use App\Models\Event;

class KnockoutSlotService
{
    public function availableSources(KnockoutMatch $match): array
    {
        $category = $match->category()->firstOrFail();

        $qualified = (int) $category->group_count
            * (int) $category->qualifiers_per_group;

        $firstRound = match ($qualified) {
            2 => 'final',
            4 => 'semifinal',
            8 => 'quarterfinal',
            16 => 'round_of_16',
            default => null,
        };

        if (! $firstRound) {
            return [];
        }

        $sources = [];

        if ($match->round === $firstRound) {
            $groups = $category->groups()
                ->orderBy('sort_order')
                ->get();

            foreach ($groups as $group) {
                for (
                    $rank = 1;
                    $rank <= (int) $category->qualifiers_per_group;
                    $rank++
                ) {
                    $key = "group:{$group->id}:{$rank}";

                    $label = $rank === 1
                        ? "Juara {$group->name}"
                        : "Runner-up {$group->name}";

                    $sources[$key] = [
                        'label' => $label,
                        'source_type' => 'group_rank',
                        'source_group_id' => $group->id,
                        'source_rank' => $rank,
                        'source_match_id' => null,
                    ];
                }
            }
        } else {
            $previousRound = match ($match->round) {
                'quarterfinal' => 'round_of_16',
                'semifinal' => 'quarterfinal',
                'final', 'third_place' => 'semifinal',
                default => null,
            };

            if (! $previousRound) {
                return [];
            }

            if (
                $match->round === 'third_place' &&
                (
                    ! $category->has_third_place_match ||
                    $qualified < 4
                )
            ) {
                return [];
            }

            $sourceType = $match->round === 'third_place'
                ? 'match_loser'
                : 'match_winner';

            $previousMatches = $category->knockoutMatches()
                ->where('round', $previousRound)
                ->orderBy('position')
                ->get();

            $roundLabel = match ($previousRound) {
                'round_of_16' => 'Babak 16 Besar',
                'quarterfinal' => 'Perempat Final',
                'semifinal' => 'Semifinal',
            };

            foreach ($previousMatches as $previousMatch) {
                $key = "{$sourceType}:{$previousMatch->id}";

                $resultLabel = $sourceType === 'match_loser'
                    ? 'Yang kalah di'
                    : 'Pemenang';

                $sources[$key] = [
                    'label' => "{$resultLabel} {$roundLabel} "
                        . $previousMatch->position,
                    'source_type' => $sourceType,
                    'source_group_id' => null,
                    'source_rank' => null,
                    'source_match_id' => $previousMatch->id,
                ];
            }
        }

        $usedSlots = KnockoutSlot::query()
            ->whereHas(
                'match',
                fn ($query) => $query->where(
                    'event_category_id',
                    $category->id
                )
            )
            ->where('knockout_match_id', '!=', $match->id)
            ->get();

        foreach ($usedSlots as $slot) {
            unset($sources[$this->sourceKey($slot)]);
        }

        return $sources;
    }

    public function sourceKey(KnockoutSlot $slot): string
    {
        if ($slot->source_type === 'group_rank') {
            return "group:{$slot->source_group_id}:{$slot->source_rank}";
        }

        return "{$slot->source_type}:{$slot->source_match_id}";
    }

    public function save(
        KnockoutMatch $match,
        string $slotA,
        string $slotB,
    ): void {

        $lock = DB::table('schedule_locks')
            ->where('name', 'global')
            ->lockForUpdate()
            ->first();

        if ($lock === null) {
            throw new DomainException(
                'Pengunci penjadwalan belum tersedia. Jalankan migration terlebih dahulu.'
            );
        }

        DB::transaction(function () use ($match, $slotA, $slotB): void {
            $category = EventCategory::query()
                ->lockForUpdate()
                ->findOrFail($match->event_category_id);

            $event = Event::query()
                ->findOrFail($category->event_id);

            if ($event->hasSchedules()) {
                throw new DomainException(
                    'Event sudah memiliki jadwal. Sumber slot bagan gugur '
                    . 'tidak dapat diubah karena memengaruhi peserta '
                    . 'dan urutan pertandingan.'
                );
            }

            if ($category->event->status !== 'draft') {
                throw new DomainException(
                    'Slot bagan hanya dapat diatur saat event berstatus Draft.'
                );
            }

            $currentMatch = $category->knockoutMatches()
                ->lockForUpdate()
                ->findOrFail($match->id);

            if ($slotA === $slotB) {
                throw new DomainException(
                    'Sumber slot A dan B harus berbeda.'
                );
            }

            $sources = $this->availableSources($currentMatch);

            if (
                ! isset($sources[$slotA]) ||
                ! isset($sources[$slotB])
            ) {
                throw new DomainException(
                    'Sumber slot tidak sesuai atau sudah digunakan '
                    . 'pada pertandingan lain. Buka ulang form dan pilih kembali.'
                );
            }

            // Hapus kedua slot lama dalam transaksi agar A dan B
            // bisa ditukar tanpa berbenturan dengan constraint unik.
            $currentMatch->slots()->delete();

            foreach (['A' => $slotA, 'B' => $slotB] as $side => $key) {
                $source = $sources[$key];

                $currentMatch->slots()->create([
                    'side' => $side,
                    'source_type' => $source['source_type'],
                    'source_group_id' => $source['source_group_id'],
                    'source_rank' => $source['source_rank'],
                    'source_match_id' => $source['source_match_id'],
                ]);
            }
        });
    }
}