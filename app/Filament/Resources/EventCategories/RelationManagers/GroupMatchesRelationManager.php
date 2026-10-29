<?php

namespace App\Filament\Resources\EventCategories\RelationManagers;

use App\Filament\Resources\EventCategories\EventCategoryResource;
use App\Models\GroupMatch;
use App\Models\TournamentPair;
use App\Services\GroupMatchGenerator;
use DomainException;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GroupMatchesRelationManager extends RelationManager
{
    protected static string $relationship = 'groupMatches';

    protected static ?string $title = 'Pertandingan Grup';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Pertandingan Fase Grup')
            ->description(
                'Daftar pertemuan round-robin. Court dan waktu '
                . 'belum ditentukan pada tahap ini.'
            )
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query->with([
                    'group',
                    'pairOne.playerOne',
                    'pairOne.playerTwo',
                    'pairTwo.playerOne',
                    'pairTwo.playerTwo',
                ])
            )
            ->columns([
                TextColumn::make('id')
                    ->label('ID Match'),

                TextColumn::make('group.name')
                    ->label('Grup')
                    ->badge(),

                TextColumn::make('round_number')
                    ->label('Putaran')
                    ->alignCenter(),

                TextColumn::make('pair_one_label')
                    ->label('Pasangan A')
                    ->getStateUsing(
                        fn (GroupMatch $record): string =>
                            $this->pairLabel($record->pairOne)
                    )
                    ->wrap(),

                TextColumn::make('pair_two_label')
                    ->label('Pasangan B')
                    ->getStateUsing(
                        fn (GroupMatch $record): string =>
                            $this->pairLabel($record->pairTwo)
                    )
                    ->wrap(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(
                        fn (string $state): string => match ($state) {
                            'pending' => 'Pending',
                            'completed' => 'Selesai',
                            default => $state,
                        }
                    )
                    ->color(
                        fn (string $state): string => match ($state) {
                            'completed' => 'success',
                            default => 'gray',
                        }
                    ),
            ])
            ->filters([
                SelectFilter::make('tournament_group_id')
                    ->label('Grup')
                    ->options(
                        fn (): array => $this->getOwnerRecord()
                            ->groups()
                            ->orderBy('sort_order')
                            ->pluck('name', 'id')
                            ->all()
                    ),
            ])
            ->headerActions([
                Action::make('generateGroupMatches')
                    ->label('Buat Pertandingan Grup')
                    ->icon(Heroicon::OutlinedPlay)
                    ->authorize(
                        fn (): bool => EventCategoryResource::canEdit(
                            $this->getOwnerRecord()
                        )
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Buat Pertandingan Round-Robin')
                    ->modalDescription(
                        'Seluruh pasangan harus sudah terdaftar dan terbagi '
                        . 'rata ke grup. Setelah pertandingan dibuat, '
                        . 'penempatan grup dikunci dan generate ulang ditolak.'
                    )
                    ->modalSubmitActionLabel('Buat Pertandingan')
                    ->action(function (): void {
                        try {
                            $count = app(GroupMatchGenerator::class)
                                ->generate($this->getOwnerRecord());
                        } catch (DomainException $exception) {
                            Notification::make()
                                ->title('Pertandingan belum dapat dibuat')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();

                            throw new Halt();
                        }

                        Notification::make()
                            ->title('Pertandingan grup berhasil dibuat')
                            ->body("Sebanyak {$count} pertandingan dibuat.")
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([])
            ->toolbarActions([])
            ->defaultSort(
                fn (Builder $query): Builder => $query
                    ->orderBy('group_matches.tournament_group_id')
                    ->orderBy('group_matches.round_number')
                    ->orderBy('group_matches.id')
            )
            ->emptyStateHeading('Belum ada pertandingan grup')
            ->emptyStateDescription(
                'Lengkapi peserta dan pembagian grup, lalu buat pertandingan.'
            )
            ->emptyStateIcon(Heroicon::OutlinedListBullet);
    }

    protected function pairLabel(?TournamentPair $pair): string
    {
        if (! $pair) {
            return 'Pasangan tidak ditemukan';
        }

        $first = $pair->playerOne?->name ?? 'Pemain tidak ditemukan';
        $second = $pair->playerTwo?->name ?? 'Pemain tidak ditemukan';

        return "{$first} / {$second}";
    }
}