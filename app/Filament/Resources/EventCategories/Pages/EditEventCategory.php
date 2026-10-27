<?php

namespace App\Filament\Resources\EventCategories\Pages;

use App\Filament\Resources\EventCategories\EventCategoryResource;
use App\Filament\Resources\Events\EventResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditEventCategory extends EditRecord
{
    protected static string $resource = EventCategoryResource::class;

    public function getTitle(): string
    {
        return 'Kelola Pasangan: ' . $this->getRecord()->name;
    }

    public function getSubheading(): ?string
    {
        $category = $this->getRecord();

        return $category->event->name
            . ' • Kapasitas: '
            . ($category->pair_count ?? 'Belum diatur')
            . ' pasangan';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToEvent')
                ->label('Kembali ke Event')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->url(fn (): string => EventResource::getUrl('edit', [
                    'record' => $this->getRecord()->event_id,
                ])),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }
}