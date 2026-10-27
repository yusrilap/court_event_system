<?php

namespace App\Filament\Resources\Players\Tables;

use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlayersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID Pemain')
                    ->formatStateUsing(
                        fn ($state): string => 'PLY-' . str_pad(
                            (string) $state,
                            5,
                            '0',
                            STR_PAD_LEFT
                        )
                    )
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Nama Lengkap')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('nickname')
                    ->label('Nama Panggilan')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('city')
                    ->label('Kota / Domisili')
                    ->icon(Heroicon::OutlinedMapPin)
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('phone')
                    ->label('WhatsApp')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Terdaftar')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Edit')
                    ->icon(Heroicon::OutlinedPencilSquare),
            ])
            ->toolbarActions([])
            ->defaultSort('name')
            ->emptyStateHeading('Belum ada pemain')
            ->emptyStateDescription(
                'Tambahkan pemain untuk digunakan saat mendaftarkan pasangan.'
            )
            ->emptyStateIcon(Heroicon::OutlinedUsers);
    }
}