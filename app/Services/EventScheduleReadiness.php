<?php

namespace App\Services;

use App\Models\Event;
use App\Models\MatchSchedule;
use DomainException;
use Illuminate\Database\Eloquent\Builder;

class EventScheduleReadiness
{
    public function __construct(
        private EventScheduleSettings $settingsValidator,
        private GroupMatchReadiness $groupValidator,
        private KnockoutBracketValidator $bracketValidator,
    ) {
    }

    public function validate(Event $event): array
    {
        $settings = $this->settingsValidator->validate($event);

        $eventId = (int) $settings['event_id'];

        $hasSchedules = MatchSchedule::query()
            ->where(function (Builder $query) use ($eventId): void {
                $query
                    ->whereHas(
                        'groupMatch.group.category',
                        fn (Builder $categoryQuery) =>
                            $categoryQuery->where('event_id', $eventId)
                    )
                    ->orWhereHas(
                        'knockoutMatch.category',
                        fn (Builder $categoryQuery) =>
                            $categoryQuery->where('event_id', $eventId)
                    );
            })
            ->exists();

        if ($hasSchedules) {
            throw new DomainException(
                'Event sudah memiliki jadwal. Generate awal tidak dapat dijalankan ulang.'
            );
        }

        $event = Event::query()
            ->with('categories')
            ->findOrFail($eventId);

        foreach ($event->categories->sortBy('id') as $category) {
            $this->groupValidator->validate($category);

            $bracketErrors = $this->bracketValidator->errors($category);

            if (count($bracketErrors) > 0) {
                throw new DomainException(
                    "Bagan gugur kategori {$category->name} belum siap:\n- "
                    . implode("\n- ", $bracketErrors)
                );
            }
        }

        return $settings;
    }
}