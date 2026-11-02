<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventCategory;
use App\Models\GroupMatch;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class GroupMatchResultService
{
    public function __construct(
        private MatchScoreValidator $scoreValidator,
    ) {
    }

    public function record(
        GroupMatch $match,
        mixed $scoreOne,
        mixed $scoreTwo,
        User $actor,
    ): GroupMatch {
        return DB::transaction(function () use (
            $match,
            $scoreOne,
            $scoreTwo,
            $actor
        ): GroupMatch {
            $lock = DB::table('schedule_locks')
                ->where('name', 'global')
                ->lockForUpdate()
                ->first();

            if ($lock === null) {
                throw new DomainException(
                    'Pengunci penjadwalan belum tersedia.'
                );
            }

            $match = GroupMatch::query()
                ->with('group')
                ->findOrFail($match->getKey());

            if ($match->group === null) {
                throw new DomainException(
                    'Grup pertandingan tidak ditemukan.'
                );
            }

            $category = EventCategory::query()
                ->findOrFail($match->group->event_category_id);

            $event = Event::query()
                ->lockForUpdate()
                ->findOrFail($category->event_id);

            $category = EventCategory::query()
                ->lockForUpdate()
                ->findOrFail($category->getKey());

            $match = GroupMatch::query()
                ->lockForUpdate()
                ->findOrFail($match->getKey());

            if ($event->status !== 'ongoing') {
                throw new DomainException(
                    'Hasil hanya dapat dicatat saat event berstatus berlangsung.'
                );
            }

            if (! $match->schedule()->exists()) {
                throw new DomainException(
                    'Pertandingan belum memiliki jadwal.'
                );
            }

            if (
                $match->status !== 'pending' ||
                $match->score_one !== null ||
                $match->score_two !== null ||
                $match->result_recorded_at !== null ||
                $match->result_recorded_by !== null ||
                $match->result_scoring_format !== null ||
                $match->result_game_count !== null
            ) {
                throw new DomainException(
                    'Pertandingan sudah memiliki hasil atau data hasil sebagian. '
                    . 'Pencatatan biasa tidak dapat menimpanya.'
                );
            }

            if (
                ! $actor->exists ||
                ! User::query()->whereKey($actor->getKey())->exists()
            ) {
                throw new DomainException(
                    'Pengguna pencatat hasil tidak ditemukan.'
                );
            }

            $rule = $category->scoringRules()
                ->where('phase', 'group')
                ->lockForUpdate()
                ->first();

            if ($rule === null) {
                throw new DomainException(
                    'Atur format skor fase grup terlebih dahulu.'
                );
            }

            $result = $this->scoreValidator->validate(
                $rule,
                $scoreOne,
                $scoreTwo
            );

            $recordedAt = now();

            // Hasil pertama mengunci aturan seluruh fase grup
            // dalam kategori ini.
            if (! $rule->isLocked()) {
                $rule->locked_at = $recordedAt;
                $rule->save();
            }

            $match->score_one = $result['score_a'];
            $match->score_two = $result['score_b'];
            $match->result_scoring_format = $rule->format;
            $match->result_game_count = $rule->game_count;
            $match->result_recorded_at = $recordedAt;
            $match->result_recorded_by = $actor->getKey();
            $match->status = 'completed';

            $match->save();

            return $match;
        }, 3);
    }
}