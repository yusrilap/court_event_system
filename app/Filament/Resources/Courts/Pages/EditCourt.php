<?php

namespace App\Filament\Resources\Courts\Pages;

use App\Filament\Resources\Courts\CourtResource;
use App\Models\Court;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditCourt extends EditRecord
{
    protected static string $resource = CourtResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function handleRecordUpdate(
        Model $record,
        array $data,
    ): Model {
        return DB::transaction(function () use ($record, $data): Model {
            $court = Court::query()
                ->lockForUpdate()
                ->findOrFail($record->getKey());

            $court->fill($data);

            if ($court->isDirty(['location_id', 'sport'])) {
                $eventLink = DB::table('event_court')
                    ->where('court_id', $court->id)
                    ->lockForUpdate()
                    ->first();

                if ($eventLink) {
                    Notification::make()
                        ->title('Lokasi dan olahraga tidak dapat diubah')
                        ->body(
                            'Court sudah digunakan oleh event. '
                            . 'Muat ulang halaman untuk melihat data terbaru.'
                        )
                        ->danger()
                        ->send();

                    throw new Halt();
                }
            }

            $court->save();

            return $court;
        });
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Data court berhasil diperbarui';
    }
}