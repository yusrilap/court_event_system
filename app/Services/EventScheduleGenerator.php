<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventCategory;
use App\Models\MatchSchedule;
use DomainException;
use Illuminate\Support\Facades\DB;

class EventScheduleGenerator
{
    public function __construct(
        private ExistingScheduleBookings $existingBookings,
        private EventSchedulePlanner $planner,
    ) {
    }

    public function generate(Event $event): int
    {
        $eventId = (int) $event->getKey();

        return DB::transaction(function () use ($eventId): int {
            // Harus dikunci sebelum membaca booking dan membuat rancangan.
            $lock = DB::table('schedule_locks')
                ->where('name', 'global')
                ->lockForUpdate()
                ->first();

            if ($lock === null) {
                throw new DomainException(
                    'Pengunci penjadwalan belum tersedia. Periksa migration schedule_locks.'
                );
            }

            $event = Event::query()
                ->lockForUpdate()
                ->findOrFail($eventId);

            // Selaras dengan service pasangan, grup, dan bagan
            // yang juga mengunci kategori sebelum mengubah data.
            EventCategory::query()
                ->where('event_id', $eventId)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            // Booking dan rancangan dihitung ulang di dalam transaksi.
            // Tidak menggunakan hasil pratinjau yang mungkin sudah berubah.
            $bookings = $this->existingBookings->load($event);

            $plan = $this->planner->plan($event, $bookings);

            if (count($plan) === 0) {
                throw new DomainException(
                    'Tidak ada pertandingan yang dapat dijadwalkan.'
                );
            }

            foreach ($plan as $row) {
                MatchSchedule::create([
                    'group_match_id' => $row['group_match_id'],
                    'knockout_match_id' => $row['knockout_match_id'],
                    'court_id' => $row['court_id'],
                    'planned_start_at' => $row['planned_start_at'],
                    'planned_end_at' => $row['planned_end_at'],
                ]);
            }

            return count($plan);
        }, 3);
    }
}