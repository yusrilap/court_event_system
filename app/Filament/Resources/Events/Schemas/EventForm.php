<?php

namespace App\Filament\Resources\Events\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use App\Models\Event;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Event')
                    ->description('Informasi utama turnamen padel atau tenis.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Event')
                            ->placeholder('Sunday Morning Court Internal Tournament')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (
                                Get $get,
                                Set $set,
                                ?string $old,
                                ?string $state,
                                string $operation,
                            ): void {
                                if ($operation !== 'create') {
                                    return;
                                }

                                if (
                                    blank($get('slug')) ||
                                    $get('slug') === Str::slug($old ?? '')
                                ) {
                                    $set('slug', Str::slug($state ?? ''));
                                }
                            })
                            ->columnSpanFull(),

                        TextInput::make('slug')
                            ->label('Slug Event')
                            ->helperText(
                                'Alamat unik event. Gunakan huruf kecil, angka, dan tanda hubung.'
                            )
                            ->required()
                            ->maxLength(255)
                            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                            ->unique(ignoreRecord: true)
                            ->columnSpanFull(),

                        Select::make('sport')
                            ->label('Cabang Olahraga')
                            ->options([
                                'padel' => 'Padel',
                                'tennis' => 'Tenis',
                            ])
                            ->required()
                            ->native(false)
                            ->disabled(function (?Event $record): bool {
                                if (! $record) {
                                    return false;
                                }

                                return $record->status !== 'draft'
                                    || $record->categories()->exists()
                                    || $record->courts()->exists();
                            })
                            ->helperText(
                                'Cabang olahraga dikunci setelah kategori atau court ditambahkan, '
                                . 'atau setelah event tidak lagi berstatus Draft.'
                            ),

                        DatePicker::make('event_date')
                            ->label('Tanggal Event')
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->locale('id'),

                        TextInput::make('location')
                            ->label('Lokasi')
                            ->placeholder('Nama venue dan alamat')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Textarea::make('description')
                            ->label('Deskripsi')
                            ->placeholder('Tuliskan informasi umum event.')
                            ->rows(4)
                            ->maxLength(10000)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Technical Meeting dan Peraturan')
                    ->description('Informasi untuk panitia dan peserta.')
                    ->schema([
                        Textarea::make('technical_meeting')
                            ->label('Informasi Technical Meeting')
                            ->placeholder(
                                'Tanggal, jam, lokasi atau tautan, serta agenda technical meeting.'
                            )
                            ->rows(4)
                            ->maxLength(10000),

                        Textarea::make('rules')
                            ->label('Peraturan Event')
                            ->placeholder('Tuliskan peraturan umum turnamen.')
                            ->rows(6)
                            ->maxLength(10000),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}