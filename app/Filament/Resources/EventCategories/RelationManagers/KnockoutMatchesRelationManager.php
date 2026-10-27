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

class KnockoutMatchesRelationManager extends RelationManager
{
    protected static string $relationship = 'knockoutMatches';

    protected static ?string $title = 'Bagan Gugur';

    public function table(Table $table): Table
    {
        return $table
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
                Action::make('createBracket')
                    ->label('Buat Struktur Bagan')
                    ->icon(Heroicon::OutlinedTrophy)
                    ->authorize(
                        fn (): bool => EventCategoryResource::canEdit(
                            $this->getOwnerRecord()
                        )
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Buat Struktur Bagan Gugur')
                    ->modalDescription(
                        'Pertandingan akan dibuat sesuai jumlah pasangan lolos. '
                        . 'Sumber peserta setiap slot diatur setelah struktur dibuat. '
                        . 'Bagan yang sudah ada tidak akan ditimpa.'
                    )
                    ->modalSubmitActionLabel('Buat Struktur')
                    ->action(fn () => $this->createBracketStructure()),
            ])
            ->recordActions([])
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
}