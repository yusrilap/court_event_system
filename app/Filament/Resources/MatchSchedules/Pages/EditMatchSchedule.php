<?php

namespace App\Filament\Resources\MatchSchedules\Pages;

use App\Filament\Resources\MatchSchedules\MatchScheduleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMatchSchedule extends EditRecord
{
    protected static string $resource = MatchScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
