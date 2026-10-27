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

class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data): Model {
            $event = Event::query()
                ->lockForUpdate()
                ->findOrFail($record->getKey());

            $event->fill($data);

            if ($event->isDirty('sport')) {
                $hasCategories = $event->categories()->exists();
                $hasCourts = $event->courts()->exists();

                if (
                    $event->status !== 'draft' ||
                    $hasCategories ||
                    $hasCourts
                ) {
                    Notification::make()
                        ->title('Cabang olahraga tidak dapat diubah')
                        ->body(
                            'Event sudah memiliki kategori atau court, '
                            . 'atau tidak lagi berstatus Draft. '
                            . 'Muat ulang halaman untuk melihat data terbaru.'
                        )
                        ->danger()
                        ->send();

                    throw new Halt();
                }
            }

            $event->save();

            return $event;
        });
    }
}
