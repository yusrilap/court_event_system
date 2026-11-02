<?php

namespace App\Filament\Resources\EventCategories\RelationManagers;

use App\Filament\Resources\EventCategories\EventCategoryResource;
use App\Models\CategoryScoringRule;
use App\Services\CategoryScoringRuleService;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ScoringRulesRelationManager extends RelationManager
{
    protected static string $relationship = 'scoringRules';

    protected static ?string $title = 'Aturan Skor';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Aturan Skor per Fase')
            ->description(
                'Atur format untuk validasi skor akhir. '
                . 'Pengaturan ini terpisah dari durasi slot jadwal.'
            )
            ->recordTitleAttribute('phase')
            ->columns([
                TextColumn::make('phase')
                    ->label('Fase')
                    ->formatStateUsing(
                        fn (string $state): string =>
                            CategoryScoringRule::PHASE_LABELS[$state] ?? $state
                    ),

                TextColumn::make('format')
                    ->label('Format')
                    ->formatStateUsing(
                        fn (string $state): string =>
                            CategoryScoringRule::FORMAT_LABELS[$state] ?? $state
                    )
                    ->badge(),

                TextColumn::make('game_count')
                    ->label('Jumlah Game'),

                TextColumn::make('locked_at')
                    ->label('Dikunci Pada (WIB)')
                    ->dateTime('d/m/Y H:i')
                    ->timezone('Asia/Jakarta')
                    ->placeholder('Belum dikunci'),
            ])
            ->headerActions([
                Action::make('setScoringRule')
                    ->label('Atur Format Fase')
                    ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                    ->authorize(
                        fn (): bool => EventCategoryResource::canEdit(
                            $this->getOwnerRecord()
                        )
                    )
                    ->modalHeading('Atur Format Skor')
                    ->modalDescription(
                        'Jika fase sudah memiliki aturan, '
                        . 'penyimpanan akan memperbarui aturan fase tersebut.'
                    )
                    ->schema([
                        Select::make('phase')
                            ->label('Fase')
                            ->options(fn (): array => $this->phaseOptions())
                            ->required(),

                        ...$this->scoringFields(),
                    ])
                    ->action(
                        fn (array $data) => $this->saveRule(
                            $data['phase'],
                            $data
                        )
                    ),
            ])
            ->recordActions([
                Action::make('changeScoringRule')
                    ->label('Ubah Format')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->authorize(
                        fn (): bool => EventCategoryResource::canEdit(
                            $this->getOwnerRecord()
                        )
                    )
                    ->modalHeading('Ubah Format Skor')
                    ->fillForm(
                        fn (CategoryScoringRule $record): array => [
                            'format' => $record->format,
                            'game_count' => $record->game_count,
                        ]
                    )
                    ->schema($this->scoringFields())
                    ->action(function (
                        CategoryScoringRule $record,
                        array $data
                    ): void {
                        $this->saveRule($record->phase, $data);
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('id')
            ->emptyStateHeading('Aturan skor belum diatur')
            ->emptyStateDescription(
                'Mulai dengan mengatur format fase grup.'
            )
            ->emptyStateIcon(Heroicon::OutlinedAdjustmentsHorizontal);
    }

    private function scoringFields(): array
    {
        return [
            Select::make('format')
                ->label('Format Skor')
                ->options(CategoryScoringRule::FORMAT_LABELS)
                ->required(),

            TextInput::make('game_count')
                ->label('Jumlah Game')
                ->numeric()
                ->integer()
                ->minValue(1)
                ->maxValue(65535)
                ->required()
                ->helperText(
                    'Contoh: Race to + 4 berarti pertama mencapai 4 game. '
                    . 'Total of + 5 berarti seluruh 5 game dimainkan.'
                ),
        ];
    }

    private function phaseOptions(): array
    {
        $rounds = $this->getOwnerRecord()
            ->knockoutMatches()
            ->distinct()
            ->pluck('round')
            ->all();

        $available = array_merge(['group'], $rounds);

        return array_intersect_key(
            CategoryScoringRule::PHASE_LABELS,
            array_flip($available)
        );
    }

    private function saveRule(string $phase, array $data): void
    {
        abort_unless(
            EventCategoryResource::canEdit($this->getOwnerRecord()),
            403
        );

        try {
            $rule = app(CategoryScoringRuleService::class)->save(
                $this->getOwnerRecord(),
                $phase,
                $data['format'],
                $data['game_count'],
            );
        } catch (DomainException $exception) {
            Notification::make()
                ->title('Aturan skor tidak dapat disimpan')
                ->body($exception->getMessage())
                ->danger()
                ->persistent()
                ->send();

            throw new Halt();
        }

        Notification::make()
            ->title('Aturan skor tersimpan')
            ->body(
                CategoryScoringRule::PHASE_LABELS[$rule->phase]
                . ': '
                . $rule->formatLabel()
            )
            ->success()
            ->send();
    }
}