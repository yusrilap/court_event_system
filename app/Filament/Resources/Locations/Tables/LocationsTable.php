<?php

namespace App\Filament\Resources\Locations\Tables;

use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class LocationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Lokasi')
                    ->icon(Heroicon::OutlinedMapPin)
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('city')
                    ->label('Kota / Kabupaten')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('address')
                    ->label('Alamat')
                    ->searchable()
                    ->limit(80)
                    ->wrap()
                    ->placeholder('—'),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status Lokasi')
                    ->placeholder('Semua lokasi')
                    ->trueLabel('Aktif')
                    ->falseLabel('Nonaktif'),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Edit')
                    ->icon(Heroicon::OutlinedPencilSquare),
            ])
            ->toolbarActions([])
            ->defaultSort('name')
            ->emptyStateHeading('Belum ada lokasi')
            ->emptyStateDescription(
                'Tambahkan lokasi sebelum membuat data court.'
            )
            ->emptyStateIcon(Heroicon::OutlinedMapPin);
    }
}