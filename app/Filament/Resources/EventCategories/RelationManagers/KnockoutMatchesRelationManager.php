<?php

namespace App\Filament\Resources\EventCategories\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\EventCategories\EventCategoryResource;
use App\Models\EventCategory;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\DB;
use App\Models\KnockoutMatch;
use App\Models\KnockoutSlot;
use App\Services\KnockoutSlotService;
use DomainException;
use Filament\Forms\Components\Select;
use App\Services\KnockoutBracketValidator;

class KnockoutMatchesRelationManager extends RelationManager
{
    protected static string $relationship = 'knockoutMatches';

    protected static ?string $title = 'Bagan Gugur';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query->with([
                    'slots.sourceGroup',
                    'slots.sourceMatch',
                ])
            )
            ->heading('Pertandingan Fase Gugur')
            ->description(
                'Struktur pertandingan dan sumber peserta dapat '
                . 'disiapkan sebelum fase grup selesai.'
            )
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('round')
                    ->label('Babak')
                    ->badge()
                    ->formatStateUsing(
                        fn (string $state): string => match ($state) {
                            'round_of_16' => 'Babak 16 Besar',
                            'quarterfinal' => 'Perempat Final',
                            'semifinal' => 'Semifinal',
                            'final' => 'Final',
                            'third_place' => 'Perebutan Juara 3',
                            default => $state,
                        }
                    )
                    ->color(
                        fn (string $state): string => match ($state) {
                            'final' => 'success',
                            'third_place' => 'warning',
                            default => 'info',
                        }
                    ),

                TextColumn::make('position')
                    ->label('Nomor dalam Babak')
                    ->alignCenter(),

                TextColumn::make('source_a')
                    ->label('Sumber A')
                    ->getStateUsing(
                        fn (KnockoutMatch $record): ?string =>
                            $this->slotSourceLabel(
                                $record->slots->firstWhere('side', 'A')
                            )
                    )
                    ->placeholder('Belum diatur')
                    ->wrap(),

                TextColumn::make('source_b')
                    ->label('Sumber B')
                    ->getStateUsing(
                        fn (KnockoutMatch $record): ?string =>
                            $this->slotSourceLabel(
                                $record->slots->firstWhere('side', 'B')
                            )
                    )
                    ->placeholder('Belum diatur')
                    ->wrap(),

                TextColumn::make('slots_count')
                    ->label('Slot Terisi')
                    ->counts('slots')
                    ->formatStateUsing(
                        fn ($state): string => "{$state}/2"
                    )
                    ->alignCenter(),
            ])
            ->defaultSort(
                fn (Builder $query): Builder => $query
                    ->orderByRaw(
                        "CASE round
                            WHEN 'round_of_16' THEN 1
                            WHEN 'quarterfinal' THEN 2
                            WHEN 'semifinal' THEN 3
                            WHEN 'final' THEN 4
                            WHEN 'third_place' THEN 5
                            ELSE 6
                        END"
                    )
                    ->orderBy('position')
            )
            ->headerActions([
                Action::make('checkBracket')
                    ->label('Periksa Bagan')
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->color('gray')
                    ->authorize(
                        fn (): bool => EventCategoryResource::canView(
                            $this->getOwnerRecord()
                        )
                    )
                    ->action(function (): void {
                        $errors = app(KnockoutBracketValidator::class)
                            ->errors($this->getOwnerRecord());

                        if ($errors === []) {
                            Notification::make()
                                ->title('Struktur bagan lengkap')
                                ->body(
                                    'Semua pertandingan memiliki slot A dan B. '
                                    . 'Sumber kelolosan serta jalur menuju final '
                                    . 'dan juara 3 sesuai dengan format kategori.'
                                )
                                ->success()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Bagan belum lengkap atau belum sesuai')
                            ->body(
                                'Ditemukan ' . count($errors)
                                . ' masalah. Periksa rincian notifikasi berikut.'
                            )
                            ->warning()
                            ->persistent()
                            ->send();

                        foreach ($errors as $index => $message) {
                            Notification::make()
                                ->title('Pemeriksaan ' . ($index + 1))
                                ->body($message)
                                ->warning()
                                ->persistent()
                                ->send();
                        }
                    }),
            ])
            ->recordActions([
                Action::make('configureSlots')
                    ->label('Atur Slot')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->authorize(
                        fn (): bool => EventCategoryResource::canEdit(
                            $this->getOwnerRecord()
                        )
                    )
                    ->modalHeading('Atur Sumber Peserta')
                    ->modalSubmitActionLabel('Simpan Slot')
                    ->fillForm(function (KnockoutMatch $record): array {
                        $service = app(KnockoutSlotService::class);
                        $slots = $record->slots()->get()->keyBy('side');

                        return [
                            'slot_a' => isset($slots['A'])
                                ? $service->sourceKey($slots['A'])
                                : null,

                            'slot_b' => isset($slots['B'])
                                ? $service->sourceKey($slots['B'])
                                : null,
                        ];
                    })
                    ->schema(function (KnockoutMatch $record): array {
                        $sources = app(KnockoutSlotService::class)
                            ->availableSources($record);

                        $options = collect($sources)
                            ->mapWithKeys(fn (array $source, string $key): array => [
                                $key => $source['label'],
                            ])
                            ->all();

                        return [
                            Select::make('slot_a')
                                ->label('Sumber Peserta A')
                                ->options($options)
                                ->native(false)
                                ->required(),

                            Select::make('slot_b')
                                ->label('Sumber Peserta B')
                                ->options($options)
                                ->native(false)
                                ->different('slot_a')
                                ->validationMessages([
                                    'different' => 'Sumber peserta A dan B harus berbeda.',
                                ])
                                ->required(),
                        ];
                    })
                    ->action(function (
                        KnockoutMatch $record,
                        array $data,
                    ): void {
                        try {
                            app(KnockoutSlotService::class)->save(
                                $record,
                                $data['slot_a'],
                                $data['slot_b'],
                            );
                        } catch (DomainException $exception) {
                            Notification::make()
                                ->title('Slot belum dapat disimpan')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();

                            throw new Halt();
                        }

                        Notification::make()
                            ->title('Sumber peserta berhasil disimpan')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('Bagan gugur belum dibuat')
            ->emptyStateDescription(
                'Struktur bagan akan mengikuti jumlah pasangan '
                . 'yang lolos dari fase grup.'
            )
            ->emptyStateIcon(Heroicon::OutlinedTrophy);
    }

    protected function createBracketStructure(): void
    {
        $matchCount = DB::transaction(function (): int {
            $category = EventCategory::query()
                ->lockForUpdate()
                ->findOrFail($this->getOwnerRecord()->getKey());

            if ($category->event->status !== 'draft') {
                $this->rejectBracketCreation(
                    'Struktur bagan hanya dapat dibuat saat event berstatus Draft.'
                );
            }

            $pairCount = (int) $category->pair_count;
            $groupCount = (int) $category->group_count;
            $qualifiers = (int) $category->qualifiers_per_group;

            if (
                ! in_array($pairCount, [8, 16], true) ||
                ! in_array($groupCount, [1, 2, 4, 8], true) ||
                ! in_array($qualifiers, [1, 2], true)
            ) {
                $this->rejectBracketCreation(
                    'Lengkapi pengaturan format kategori terlebih dahulu.'
                );
            }

            if (
                $pairCount % $groupCount !== 0 ||
                intdiv($pairCount, $groupCount) < 2
            ) {
                $this->rejectBracketCreation(
                    'Format grup tidak valid. Minimal 2 pasangan per grup '
                    . 'dan pembagian harus merata.'
                );
            }

            $totalQualified = $groupCount * $qualifiers;

            if (
                ! in_array($totalQualified, [2, 4, 8, 16], true) ||
                $totalQualified > $pairCount
            ) {
                $this->rejectBracketCreation(
                    'Jumlah pasangan lolos harus 2, 4, 8, atau 16 '
                    . 'dan tidak melebihi jumlah peserta.'
                );
            }

            if (
                $category->has_third_place_match &&
                $totalQualified < 4
            ) {
                $this->rejectBracketCreation(
                    'Perebutan juara 3 memerlukan fase semifinal.'
                );
            }

            $groups = $category->groups()
                ->lockForUpdate()
                ->get();

            if ($groups->count() !== $groupCount) {
                $this->rejectBracketCreation(
                    'Buat grup sesuai pengaturan kategori terlebih dahulu.'
                );
            }

            $existingMatches = $category->knockoutMatches()
                ->lockForUpdate()
                ->get();

            if ($existingMatches->isNotEmpty()) {
                $this->rejectBracketCreation(
                    'Struktur bagan sudah ada. Bagan yang tersimpan tidak ditimpa.'
                );
            }

            $roundNames = [
                16 => 'round_of_16',
                8 => 'quarterfinal',
                4 => 'semifinal',
                2 => 'final',
            ];

            $created = 0;
            $participants = $totalQualified;

            while ($participants >= 2) {
                $round = $roundNames[$participants];
                $matchesInRound = intdiv($participants, 2);

                for ($position = 1; $position <= $matchesInRound; $position++) {
                    $category->knockoutMatches()->create([
                        'round' => $round,
                        'position' => $position,
                    ]);

                    $created++;
                }

                $participants = intdiv($participants, 2);
            }

            if ($category->has_third_place_match) {
                $category->knockoutMatches()->create([
                    'round' => 'third_place',
                    'position' => 1,
                ]);

                $created++;
            }

            return $created;
        });

        Notification::make()
            ->title('Struktur bagan berhasil dibuat')
            ->body(
                "{$matchCount} pertandingan dibuat. "
                . 'Sumber peserta pada slot A dan B belum diatur.'
            )
            ->success()
            ->send();
    }

    protected function rejectBracketCreation(string $message): never
    {
        Notification::make()
            ->title('Struktur bagan belum dapat dibuat')
            ->body($message)
            ->danger()
            ->send();

        throw new Halt();
    }

    protected function slotSourceLabel(?KnockoutSlot $slot): ?string
    {
        if (! $slot) {
            return null;
        }

        if ($slot->source_type === 'group_rank') {
            $rankLabel = $slot->source_rank === 1
                ? 'Juara'
                : 'Runner-up';

            $groupName = $slot->sourceGroup?->name
                ?? 'Grup tidak ditemukan';

            return "{$rankLabel} {$groupName}";
        }

        $sourceMatch = $slot->sourceMatch;

        if (! $sourceMatch) {
            return 'Pertandingan sumber tidak ditemukan';
        }

        $roundLabel = match ($sourceMatch->round) {
            'round_of_16' => 'Babak 16 Besar',
            'quarterfinal' => 'Perempat Final',
            'semifinal' => 'Semifinal',
            'final' => 'Final',
            'third_place' => 'Perebutan Juara 3',
            default => $sourceMatch->round,
        };

        $resultLabel = $slot->source_type === 'match_loser'
            ? 'Yang kalah di'
            : 'Pemenang';

        return "{$resultLabel} {$roundLabel} {$sourceMatch->position}";
    }
}