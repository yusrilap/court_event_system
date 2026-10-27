<?php

namespace App\Filament\Resources\Courts\Tables;

use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CourtsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Court')
                    ->icon(Heroicon::OutlinedSquares2x2)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('location.name')
                    ->label('Lokasi')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('location.city')
                    ->label('Kota / Kabupaten')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('sport')
                    ->label('Olahraga')
                    ->badge()
                    ->formatStateUsing(
                        fn (string $state): string => match ($state) {
                            'padel' => 'Padel',
                            'tennis' => 'Tenis',
                            default => $state,
                        }
                    )
                    ->color(
                        fn (string $state): string => match ($state) {
                            'padel' => 'info',
                            'tennis' => 'success',
                            default => 'gray',
                        }
                    ),

                IconColumn::make('is_active')
                    ->label('Court Aktif')
                    ->boolean()
                    ->sortable(),

                IconColumn::make('location.is_active')
                    ->label('Lokasi Aktif')
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('location_id')
                    ->label('Lokasi')
                    ->relationship('location', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('sport')
                    ->label('Cabang Olahraga')
                    ->options([
                        'padel' => 'Padel',
                        'tennis' => 'Tenis',
                    ]),

                TernaryFilter::make('is_active')
                    ->label('Status Court')
                    ->placeholder('Semua court')
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
            ->emptyStateHeading('Belum ada court')
            ->emptyStateDescription(
                'Tambahkan court dan hubungkan dengan lokasi pertandingan.'
            )
            ->emptyStateIcon(Heroicon::OutlinedSquares2x2);
    }
}