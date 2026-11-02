<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use App\Models\Event;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Services\EventStartService;
use DomainException;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;


class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            $this->startEventAction(),
        ];
    }

    protected function handleRecordUpdate(
        Model $record,
        array $data
    ): Model {
        return DB::transaction(function () use ($record, $data): Model {
            // Urutan penguncian sama dengan generator:
            // pengunci global dahulu, kemudian event.
            $lock = DB::table('schedule_locks')
                ->where('name', 'global')
                ->lockForUpdate()
                ->first();

            if ($lock === null) {
                Notification::make()
                    ->title('Pengunci penjadwalan belum tersedia')
                    ->body('Jalankan migration schedule_locks terlebih dahulu.')
                    ->danger()
                    ->send();

                throw new Halt();
            }

            $event = Event::query()
                ->lockForUpdate()
                ->findOrFail($record->getKey());

            $event->fill($data);

            $scheduleFields = [
                'sport',
                'event_date',
                'schedule_start_time',
                'court_gap_minutes',
                'player_rest_minutes',
            ];

            if (
                $event->isDirty($scheduleFields) &&
                $event->hasSchedules()
            ) {
                Notification::make()
                    ->title('Pengaturan jadwal dikunci')
                    ->body(
                        'Event sudah memiliki jadwal. '
                        . 'Cabang olahraga, tanggal, jam mulai, '
                        . 'jeda court, dan waktu istirahat pemain '
                        . 'tidak dapat diubah melalui form event.'
                    )
                    ->danger()
                    ->send();

                throw new Halt();
            }

            // Pertahankan pengaman cabang olahraga sebelumnya.
            if (
                $event->isDirty('sport') &&
                (
                    $event->status !== 'draft' ||
                    $event->categories()->exists() ||
                    $event->courts()->exists()
                )
            ) {
                Notification::make()
                    ->title('Cabang olahraga tidak dapat diubah')
                    ->body(
                        'Event harus berstatus draft serta belum '
                        . 'memiliki kategori dan court.'
                    )
                    ->danger()
                    ->send();

                throw new Halt();
            }

            $event->save();

            return $event;
        }, 3);
    }

    protected function startEventAction(): Action
    {
        return Action::make('startEvent')
            ->label('Mulai Event')
            ->icon(Heroicon::OutlinedPlay)
            ->color('success')
            ->visible(
                fn (): bool => $this->getRecord()->status === 'draft'
            )
            ->authorize(
                fn (): bool => EventResource::canEdit($this->getRecord())
            )
            ->requiresConfirmation()
            ->modalHeading('Mulai Event')
            ->modalDescription(
                fn (): string =>
                    'Mulai event "' . $this->getRecord()->name . '"? '
                    . 'Sistem akan memeriksa kelengkapan pertandingan, '
                    . 'jadwal, dan aturan skor seluruh kategori. '
                    . 'Status akan berubah menjadi berlangsung. '
                    . 'Publikasi kepada peserta tidak berubah.'
            )
            ->modalSubmitActionLabel('Ya, Mulai Event')
            ->action(function (): void {
                $event = $this->getRecord()->fresh();

                abort_unless(
                    $event !== null && EventResource::canEdit($event),
                    403
                );

                try {
                    app(EventStartService::class)->start($event);
                } catch (DomainException $exception) {
                    Notification::make()
                        ->title('Event belum dapat dimulai')
                        ->body($exception->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();

                    throw new Halt();
                }

                Notification::make()
                    ->title('Event berhasil dimulai')
                    ->body(
                        'Status event sekarang berlangsung. '
                        . 'Pencatatan hasil dapat dilakukan setelah '
                        . 'form input skor tersedia.'
                    )
                    ->success()
                    ->send();

                $this->redirect(
                    EventResource::getUrl('edit', [
                        'record' => $event->getKey(),
                    ])
                );
            });
    }
}
