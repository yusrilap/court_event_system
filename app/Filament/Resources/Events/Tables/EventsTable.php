<?php

namespace App\Filament\Resources\Events\Tables;

use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class EventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Event')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('sport')
                    ->label('Olahraga')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'padel' => 'Padel',
                        'tennis' => 'Tenis',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'padel' => 'info',
                        'tennis' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('event_date')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('location')
                    ->label('Lokasi')
                    ->icon(Heroicon::OutlinedMapPin)
                    ->searchable()
                    ->wrap()
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Status Pelaksanaan')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Draft',
                        'ongoing' => 'Berlangsung',
                        'completed' => 'Selesai',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'ongoing' => 'warning',
                        'completed' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),

                IconColumn::make('is_published')
                    ->label('Publikasi')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedEye)
                    ->falseIcon(Heroicon::OutlinedEyeSlash)
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(
                        fn ($record): string => $record->is_published
                            ? 'Dipublikasikan'
                            : 'Belum dipublikasikan'
                    ),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('sport')
                    ->label('Cabang Olahraga')
                    ->options([
                        'padel' => 'Padel',
                        'tennis' => 'Tenis',
                    ]),

                SelectFilter::make('status')
                    ->label('Status Pelaksanaan')
                    ->options([
                        'draft' => 'Draft',
                        'ongoing' => 'Berlangsung',
                        'completed' => 'Selesai',
                    ]),

                TernaryFilter::make('is_published')
                    ->label('Publikasi')
                    ->placeholder('Semua')
                    ->trueLabel('Dipublikasikan')
                    ->falseLabel('Belum dipublikasikan'),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Edit')
                    ->icon(Heroicon::OutlinedPencilSquare),
            ])
            ->toolbarActions([])
            ->defaultSort('event_date', 'desc')
            ->emptyStateHeading('Belum ada event')
            ->emptyStateDescription(
                'Buat event pertama untuk mulai mengelola turnamen.'
            )
            ->emptyStateIcon(Heroicon::OutlinedCalendarDays);
    }
}