<?php

namespace App\Filament\Resources\Courts\Schemas;

use App\Models\Court;
use App\Models\Location;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class CourtForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Court')
                    ->schema([
                        Select::make('location_id')
                            ->label('Lokasi')
                            ->relationship(
                                name: 'location',
                                titleAttribute: 'name',
                                modifyQueryUsing: function (
                                    Builder $query,
                                    ?Court $record,
                                ): Builder {
                                    return $query->where(
                                        function (Builder $query) use ($record): void {
                                            $query->where('is_active', true);

                                            if ($record?->location_id) {
                                                $query->orWhere(
                                                    'id',
                                                    $record->location_id
                                                );
                                            }
                                        }
                                    );
                                },
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn (Location $record): string =>
                                    $record->name
                                    . ($record->city ? " — {$record->city}" : '')
                                    . ($record->is_active ? '' : ' (Nonaktif)')
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required()
                            ->helperText(
                                'Pilih lokasi aktif. Lokasi lama yang nonaktif '
                                . 'tetap ditampilkan saat mengedit court terkait.'
                            )
                            ->columnSpanFull()
                            ->disabled(
                                fn (?Court $record): bool =>
                                    $record?->events()->exists() ?? false
                            ),

                        TextInput::make('name')
                            ->label('Nama Court')
                            ->placeholder('Contoh: Court 1')
                            ->required()
                            ->maxLength(100)
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn (
                                    Unique $rule,
                                    Get $get,
                                ): Unique => $rule->where(
                                    'location_id',
                                    $get('location_id')
                                ),
                            )
                            ->validationMessages([
                                'unique' => 'Nama court sudah digunakan pada lokasi ini.',
                            ]),

                        Select::make('sport')
                            ->label('Cabang Olahraga')
                            ->options([
                                'padel' => 'Padel',
                                'tennis' => 'Tenis',
                            ])
                            ->native(false)
                            ->required()
                            ->disabled(
                                fn (?Court $record): bool =>
                                    $record?->events()->exists() ?? false
                            )
                            ->helperText(
                                'Olahraga dikunci setelah court digunakan oleh event.'
                            ),

                        Textarea::make('notes')
                            ->label('Catatan Internal')
                            ->placeholder('Informasi tambahan untuk panitia')
                            ->rows(3)
                            ->maxLength(10000)
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label('Court Aktif')
                            ->default(true)
                            ->helperText(
                                'Nonaktifkan jika court tidak tersedia '
                                . 'untuk penjadwalan event baru.'
                            )
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}