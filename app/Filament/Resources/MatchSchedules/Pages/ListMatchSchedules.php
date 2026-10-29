<?php

namespace App\Filament\Resources\MatchSchedules\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\MatchSchedules\MatchScheduleResource;
use App\Models\Event;
use App\Services\EventScheduleGenerator;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

class ListMatchSchedules extends ListRecords
{
    protected static string $resource = MatchScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateSchedule')
                ->label('Generate Jadwal')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->authorize(
                    fn (): bool => EventResource::canViewAny()
                )
                ->modalHeading('Generate Jadwal Pertandingan')
                ->modalDescription(
                    'Sistem akan menyusun dan menyimpan jadwal seluruh '
                    . 'kategori dalam event. Pastikan pasangan, grup, '
                    . 'bagan gugur, dan pengaturan court sudah lengkap. '
                    . 'Generate ulang tidak tersedia setelah jadwal tersimpan.'
                )
                ->modalSubmitActionLabel('Generate dan Simpan')
                ->schema([
                    Select::make('event_id')
                        ->label('Event')
                        ->options(fn (): array => $this->availableEvents())
                        ->searchable()
                        ->required()
                        ->helperText(
                            'Hanya event draft yang belum memiliki jadwal '
                            . 'dan dapat Anda edit yang ditampilkan.'
                        ),
                ])
                ->action(function (array $data): void {
                    $event = Event::query()
                        ->findOrFail($data['event_id']);

                    // Periksa kembali izin di server untuk event terpilih.
                    abort_unless(EventResource::canEdit($event), 403);

                    try {
                        $count = app(EventScheduleGenerator::class)
                            ->generate($event);
                    } catch (DomainException $exception) {
                        Notification::make()
                            ->title('Jadwal belum dapat dibuat')
                            ->body($exception->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();

                        throw new Halt();
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->title('Data jadwal tidak valid')
                            ->body(
                                collect($exception->errors())
                                    ->flatten()
                                    ->implode(' ')
                            )
                            ->danger()
                            ->persistent()
                            ->send();

                        throw new Halt();
                    }

                    Notification::make()
                        ->title('Jadwal berhasil dibuat')
                        ->body(
                            "{$count} pertandingan untuk event "
                            . "{$event->name} berhasil dijadwalkan. "
                            . 'Gunakan filter Event untuk memeriksa hasilnya.'
                        )
                        ->success()
                        ->send();

                    $this->resetTable();
                }),
        ];
    }

    private function availableEvents(): array
    {
        return Event::query()
            ->where('status', 'draft')
            ->orderByDesc('event_date')
            ->orderBy('id')
            ->get()
            ->filter(
                fn (Event $event): bool =>
                    EventResource::canEdit($event) &&
                    ! $event->hasSchedules()
            )
            ->mapWithKeys(
                fn (Event $event): array => [
                    $event->id => "{$event->name} (#{$event->id})",
                ]
            )
            ->all();
    }
}