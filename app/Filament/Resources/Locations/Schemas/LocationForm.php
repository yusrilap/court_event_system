<?php

namespace App\Filament\Resources\Locations\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LocationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Lokasi')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Lokasi')
                            ->placeholder('Nama venue atau tempat pertandingan')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('city')
                            ->label('Kota / Kabupaten')
                            ->placeholder('Contoh: Cianjur')
                            ->maxLength(100),

                        Textarea::make('address')
                            ->label('Alamat')
                            ->rows(3)
                            ->maxLength(10000)
                            ->columnSpanFull(),

                        Textarea::make('notes')
                            ->label('Catatan Internal')
                            ->placeholder('Informasi tambahan untuk panitia')
                            ->rows(3)
                            ->maxLength(10000)
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label('Lokasi Aktif')
                            ->default(true)
                            ->helperText(
                                'Nonaktifkan jika lokasi tidak digunakan untuk event baru.'
                            )
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}