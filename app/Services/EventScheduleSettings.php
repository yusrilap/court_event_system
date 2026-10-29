<?php

namespace App\Services;

use App\Models\Event;
use Carbon\CarbonImmutable;
use DomainException;

class EventScheduleSettings
{
    public function validate(Event $event): array
    {
        // Ambil kondisi terbaru dari database.
        $event = Event::query()
            ->with([
                'courts.location',
                'categories',
            ])
            ->findOrFail($event->getKey());

        if ($event->status !== 'draft') {
            throw new DomainException(
                'Pembuatan jadwal hanya dapat dilakukan saat event berstatus draft.'
            );
        }

        if ($event->event_date === null) {
            throw new DomainException(
                'Tanggal event belum diisi.'
            );
        }

        if (blank($event->schedule_start_time)) {
            throw new DomainException(
                'Jam mulai jadwal belum diisi pada pengaturan event.'
            );
        }

        $courtGap = $this->validateMinutes(
            $event->court_gap_minutes,
            'Jeda penggunaan court'
        );

        $playerRest = $this->validateMinutes(
            $event->player_rest_minutes,
            'Waktu istirahat pemain'
        );

        if ($event->courts->isEmpty()) {
            throw new DomainException(
                'Tambahkan minimal satu court pada event.'
            );
        }

        foreach ($event->courts as $court) {
            if (! $court->is_active) {
                throw new DomainException(
                    "Court {$court->name} sedang tidak aktif."
                );
            }

            if (
                $court->location === null ||
                ! $court->location->is_active
            ) {
                throw new DomainException(
                    "Lokasi untuk court {$court->name} tidak aktif atau tidak tersedia."
                );
            }

            if ($court->sport !== $event->sport) {
                throw new DomainException(
                    "Cabang olahraga court {$court->name} tidak sesuai dengan event."
                );
            }
        }

        if ($event->categories->isEmpty()) {
            throw new DomainException(
                'Event belum memiliki kategori pertandingan.'
            );
        }

        $durations = [];

        foreach ($event->categories as $category) {
            $duration = $category->slot_duration_minutes;

            if (
                $duration === null ||
                $duration < 1 ||
                $duration > 1440
            ) {
                throw new DomainException(
                    "Durasi pertandingan kategori {$category->name} harus 1–1440 menit."
                );
            }

            $durations[$category->getKey()] = (int) $duration;
        }

        $time = (string) $event->schedule_start_time;

        if (! preg_match(
            '/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/',
            $time
        )) {
            throw new DomainException(
                'Format jam mulai tidak valid. Gunakan jam dan menit, misalnya 08:00.'
            );
        }

        if (strlen($time) === 5) {
            $time .= ':00';
        }

        // Tanggal dan jam yang diinput panitia dianggap WIB.
        $startAt = CarbonImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $event->event_date->format('Y-m-d') . ' ' . $time,
            'Asia/Jakarta'
        );

        return [
            'event_id' => $event->getKey(),
            'timezone' => 'Asia/Jakarta',

            // Penyimpanan waktu jadwal menggunakan UTC.
            'start_at_utc' => $startAt->setTimezone('UTC'),

            'court_ids' => $event->courts
                ->sortBy('id')
                ->modelKeys(),

            'court_gap_minutes' => $courtGap,
            'player_rest_minutes' => $playerRest,
            'category_duration_minutes' => $durations,
        ];
    }

    private function validateMinutes(
        mixed $value,
        string $label
    ): int {
        if ($value === null) {
            throw new DomainException(
                "{$label} belum diisi. Isi 0 jika tidak menggunakan jeda."
            );
        }

        if (
            filter_var($value, FILTER_VALIDATE_INT) === false ||
            (int) $value < 0 ||
            (int) $value > 1440
        ) {
            throw new DomainException(
                "{$label} harus berupa angka 0–1440 menit."
            );
        }

        return (int) $value;
    }
}