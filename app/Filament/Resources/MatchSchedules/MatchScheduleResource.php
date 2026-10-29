<?php

namespace App\Filament\Resources\MatchSchedules;

use App\Filament\Resources\MatchSchedules\Pages\ListMatchSchedules;
use App\Models\MatchSchedule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;
use App\Models\Event;
use App\Models\KnockoutMatch;
use App\Models\TournamentPair;
use Filament\Tables\Filters\SelectFilter;

class MatchScheduleResource extends Resource
{
    protected static ?string $model = MatchSchedule::class;

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Turnamen';

    protected static ?string $navigationLabel = 'Jadwal Pertandingan';

    protected static ?string $modelLabel = 'Jadwal Pertandingan';

    protected static ?string $pluralModelLabel = 'Jadwal Pertandingan';

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('event_name')
                    ->label('Event')
                    ->getStateUsing(
                        fn (MatchSchedule $record): string =>
                            $record->groupMatch?->group?->category?->event?->name
                            ?? $record->knockoutMatch?->category?->event?->name
                            ?? 'Event tidak ditemukan'
                    )
                    ->wrap(),

                TextColumn::make('category_name')
                    ->label('Kategori')
                    ->getStateUsing(
                        fn (MatchSchedule $record): string =>
                            $record->groupMatch?->group?->category?->name
                            ?? $record->knockoutMatch?->category?->name
                            ?? 'Kategori tidak ditemukan'
                    )
                    ->wrap(),

                TextColumn::make('phase')
                    ->label('Fase')
                    ->getStateUsing(
                        fn (MatchSchedule $record): string =>
                            $record->group_match_id !== null
                                ? 'Grup'
                                : 'Gugur'
                    )
                    ->badge(),

                TextColumn::make('planned_start_at')
                    ->label('Mulai (WIB)')
                    ->dateTime('d/m/Y H:i')
                    ->timezone('Asia/Jakarta')
                    ->sortable(),

                TextColumn::make('planned_end_at')
                    ->label('Selesai Rencana (WIB)')
                    ->dateTime('d/m/Y H:i')
                    ->timezone('Asia/Jakarta'),

                TextColumn::make('court.name')
                    ->label('Court')
                    ->searchable()
                    ->description(
                        fn (MatchSchedule $record): ?string =>
                            $record->court?->location?->name
                    ),

                TextColumn::make('actual_start_at')
                    ->label('Mulai Aktual (WIB)')
                    ->dateTime('d/m/Y H:i')
                    ->timezone('Asia/Jakarta')
                    ->placeholder('Belum dicatat')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('actual_end_at')
                    ->label('Selesai Aktual (WIB)')
                    ->dateTime('d/m/Y H:i')
                    ->timezone('Asia/Jakarta')
                    ->placeholder('Belum dicatat')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])

            ->filters([
                SelectFilter::make('event_id')
                    ->label('Event')
                    ->options(
                        fn (): array => Event::query()
                            ->orderByDesc('event_date')
                            ->orderBy('id')
                            ->get()
                            ->mapWithKeys(fn (Event $event): array => [
                                $event->id => "{$event->name} (#{$event->id})",
                            ])
                            ->all()
                    )
                    ->searchable()
                    ->query(function (Builder $query, array $data): Builder {
                        $eventId = $data['value'] ?? null;

                        if (blank($eventId)) {
                            return $query;
                        }

                        return $query->where(
                            function (Builder $scheduleQuery) use ($eventId): void {
                                $scheduleQuery
                                    ->whereHas(
                                        'groupMatch.group.category',
                                        fn (Builder $categoryQuery): Builder =>
                                            $categoryQuery->where('event_id', $eventId)
                                    )
                                    ->orWhereHas(
                                        'knockoutMatch.category',
                                        fn (Builder $categoryQuery): Builder =>
                                            $categoryQuery->where('event_id', $eventId)
                                    );
                            }
                        );
                    }),
            ])

            ->recordActions([])
            ->toolbarActions([])
            ->recordUrl(null)
            ->defaultSort(
                fn (Builder $query): Builder => $query
                    ->orderBy('planned_start_at')
                    ->orderBy('court_id')
                    ->orderBy('id')
            )
            ->emptyStateHeading('Belum ada jadwal pertandingan')
            ->emptyStateDescription(
                'Jadwal akan tampil setelah proses generate berhasil disimpan.'
            )
            ->emptyStateIcon(Heroicon::OutlinedCalendarDays);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'groupMatch.group.category.event',
                'groupMatch.pairOne.playerOne',
                'groupMatch.pairOne.playerTwo',
                'groupMatch.pairTwo.playerOne',
                'groupMatch.pairTwo.playerTwo',
                'knockoutMatch.category.event',
                'knockoutMatch.slots.sourceGroup',
                'knockoutMatch.slots.sourceMatch',
                'court.location',
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMatchSchedules::route('/'),
        ];
    }

    private static function pairLabel(?TournamentPair $pair): string
{
    if ($pair === null) {
        return 'Pasangan tidak ditemukan';
    }

    return ($pair->playerOne?->name ?? 'Pemain tidak ditemukan')
        . ' / '
        . ($pair->playerTwo?->name ?? 'Pemain tidak ditemukan');
}

private static function knockoutLabel(?KnockoutMatch $match): string
{
    if ($match === null) {
        return 'Pertandingan tidak ditemukan';
    }

    $round = match ($match->round) {
        'round_of_16' => 'Babak 16 Besar',
        'quarterfinal' => 'Perempat Final',
        'semifinal' => 'Semifinal',
        'final' => 'Final',
        'third_place' => 'Perebutan Juara 3',
        default => 'Babak tidak dikenali',
    };

    if (in_array($match->round, ['final', 'third_place'], true)) {
        return $round;
    }

    return "{$round} {$match->position}";
}

private static function participantLabel(
        MatchSchedule $record,
        string $side
    ): string {
        if ($record->group_match_id !== null) {
            return static::pairLabel(
                $side === 'A'
                    ? $record->groupMatch?->pairOne
                    : $record->groupMatch?->pairTwo
            );
        }

        $slot = $record->knockoutMatch?->slots->firstWhere('side', $side);

        if ($slot === null) {
            return 'Sumber slot belum diatur';
        }

        if ($slot->source_type === 'group_rank') {
            $groupName = $slot->sourceGroup?->name;

            if ($groupName === null) {
                return 'Grup sumber tidak ditemukan';
            }

            return match ((int) $slot->source_rank) {
                1 => "Juara {$groupName}",
                2 => "Runner-up {$groupName}",
                default => "Peringkat {$slot->source_rank} {$groupName}",
            };
        }

        if ($slot->sourceMatch === null) {
            return 'Pertandingan sumber tidak ditemukan';
        }

        $sourceLabel = static::knockoutLabel($slot->sourceMatch);

        return match ($slot->source_type) {
            'match_winner' => "Pemenang {$sourceLabel}",
            'match_loser' => "Pihak kalah {$sourceLabel}",
            default => 'Sumber slot tidak valid',
        };
    }
}