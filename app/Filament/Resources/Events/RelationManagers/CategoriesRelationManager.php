<?php

namespace App\Filament\Resources\Events\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

class CategoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'categories';

    protected static ?string $title = 'Kategori Pertandingan';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Kategori')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Kategori')
                            ->placeholder('Contoh: Upper Beginner')
                            ->required()
                            ->maxLength(150)
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule): Unique =>
                                    $rule->where(
                                        'event_id',
                                        $this->getOwnerRecord()->getKey()
                                    ),
                            )
                            ->validationMessages([
                                'unique' => 'Nama kategori ini sudah ada dalam event ini.',
                            ]),

                        TextInput::make('slot_duration_minutes')
                            ->label('Durasi Slot Pertandingan')
                            ->helperText(
                                'Alokasi waktu pada jadwal, bukan aturan penentuan pemenang.'
                            )
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(1440)
                            ->default(20)
                            ->suffix('menit')
                            ->required(),

                        Textarea::make('description')
                            ->label('Deskripsi Kategori')
                            ->rows(3)
                            ->maxLength(10000)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Format Pertandingan')
                    ->description(
                        'Fase grup menggunakan satu putaran: setiap pasangan bertemu '
                        . 'semua pasangan lain dalam grup satu kali.'
                    )
                    ->schema([
                        Select::make('pair_count')
                            ->label('Jumlah Pasangan')
                            ->options([
                                8 => '8 pasangan',
                                16 => '16 pasangan',
                            ])
                            ->required()
                            ->native(false)
                            ->live(),

                        Select::make('group_count')
                            ->label('Jumlah Grup')
                            ->options([
                                1 => '1 grup',
                                2 => '2 grup',
                                4 => '4 grup',
                                8 => '8 grup',
                            ])
                            ->required()
                            ->native(false)
                            ->live()
                            ->helperText(function (Get $get): string {
                                $pairs = (int) $get('pair_count');
                                $groups = (int) $get('group_count');

                                if ($pairs < 1 || $groups < 1) {
                                    return 'Minimal 2 pasangan per grup.';
                                }

                                if ($pairs % $groups !== 0) {
                                    return 'Jumlah pasangan harus terbagi rata.';
                                }

                                return ($pairs / $groups) . ' pasangan per grup.';
                            })
                            ->rules([
                                fn (Get $get): Closure => function (
                                    string $attribute,
                                    $value,
                                    Closure $fail,
                                ) use ($get): void {
                                    $pairs = (int) $get('pair_count');
                                    $groups = (int) $value;

                                    if ($pairs < 1 || $groups < 1) {
                                        return;
                                    }

                                    if ($pairs % $groups !== 0) {
                                        $fail('Jumlah pasangan harus terbagi rata ke grup.');

                                        return;
                                    }

                                    if (($pairs / $groups) < 2) {
                                        $fail('Setiap grup harus berisi minimal 2 pasangan.');
                                    }
                                },
                            ]),

                        Select::make('qualifiers_per_group')
                            ->label('Lolos per Grup')
                            ->options([
                                1 => 'Juara grup saja',
                                2 => 'Juara dan runner-up',
                            ])
                            ->required()
                            ->native(false)
                            ->live()
                            ->helperText(function (Get $get): string {
                                $groups = (int) $get('group_count');
                                $qualifiers = (int) $get('qualifiers_per_group');

                                if ($groups < 1 || $qualifiers < 1) {
                                    return 'Pilih jumlah pasangan yang lolos.';
                                }

                                $total = $groups * $qualifiers;

                                $round = match ($total) {
                                    2 => 'Final',
                                    4 => 'Semifinal',
                                    8 => 'Perempat final',
                                    16 => 'Babak 16 besar',
                                    default => 'Kombinasi belum valid',
                                };

                                return "{$total} pasangan lolos — {$round}.";
                            })
                            ->rules([
                                fn (Get $get): Closure => function (
                                    string $attribute,
                                    $value,
                                    Closure $fail,
                                ) use ($get): void {
                                    $groups = (int) $get('group_count');
                                    $qualifiers = (int) $value;
                                    $pairs = (int) $get('pair_count');

                                    if ($groups < 1 || $qualifiers < 1) {
                                        return;
                                    }

                                    $total = $groups * $qualifiers;

                                    if (! in_array($total, [2, 4, 8, 16], true)) {
                                        $fail(
                                            'Total pasangan lolos harus 2, 4, 8, atau 16.'
                                        );
                                    }

                                    if ($pairs > 0 && $total > $pairs) {
                                        $fail(
                                            'Jumlah yang lolos tidak boleh melebihi '
                                            . 'jumlah pasangan peserta.'
                                        );
                                    }
                                },
                            ]),

                        Toggle::make('has_third_place_match')
                            ->label('Adakan Perebutan Juara 3')
                            ->helperText(
                                'Memerlukan semifinal: minimal 4 pasangan lolos.'
                            )
                            ->default(false)
                            ->rules([
                                fn (Get $get): Closure => function (
                                    string $attribute,
                                    $value,
                                    Closure $fail,
                                ) use ($get): void {
                                    if (! $value) {
                                        return;
                                    }

                                    $total = (int) $get('group_count')
                                        * (int) $get('qualifiers_per_group');

                                    if (! in_array($total, [4, 8, 16], true)) {
                                        $fail(
                                            'Perebutan juara 3 memerlukan fase semifinal. '
                                            . 'Atur minimal 4 pasangan lolos.'
                                        );
                                    }
                                },
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modelLabel('Kategori')
            ->pluralModelLabel('Kategori Pertandingan')
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Kategori')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slot_duration_minutes')
                    ->label('Durasi Slot')
                    ->suffix(' menit')
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Deskripsi')
                    ->limit(60)
                    ->placeholder('Belum diisi')
                    ->wrap(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah Kategori')
                    ->icon(Heroicon::OutlinedPlus)
                    ->modalHeading('Tambah Kategori Pertandingan'),
            ])
            ->recordActions([ 
                EditAction::make()
                    ->label('Edit')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->modalHeading('Edit Kategori Pertandingan'),
            ])
            ->toolbarActions([])
            ->defaultSort('name')
            ->emptyStateHeading('Belum ada kategori')
            ->emptyStateDescription(
                'Tambahkan kategori pertandingan untuk event ini.'
            )
            ->emptyStateIcon(Heroicon::OutlinedSquares2x2);
    }
}