<?php

namespace App\Filament\Resources\EventCategories\RelationManagers;

use App\Models\EventCategory;
use App\Models\Player;
use App\Models\TournamentPair;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use App\Filament\Resources\Players\PlayerResource;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;

class PairsRelationManager extends RelationManager
{
    protected static string $relationship = 'pairs';

    protected static ?string $title = 'Pasangan Peserta';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('player_one_id')
                    ->label('Pemain Pertama')
                    ->relationship('playerOne', 'name')
                    ->getOptionLabelFromRecordUsing(
                        fn (Player $record): string =>
                            $this->playerLabel($record)
                    )
                    ->searchable(['name', 'nickname', 'city'])
                    ->preload()
                    ->helperText(
                        'Cari pemain dahulu. Klik + jika pemain belum terdaftar.'
                    )
                    ->createOptionForm($this->newPlayerFields())
                    ->createOptionAction(
                        fn (Action $action): Action => $action
                            ->modalHeading('Tambah Pemain Baru')
                            ->modalSubmitActionLabel('Simpan Pemain')
                            ->authorize(
                                fn (): bool => PlayerResource::canCreate()
                            )
                    )
                    ->required(),

                Select::make('player_two_id')
                    ->label('Pemain Kedua')
                    ->relationship('playerTwo', 'name')
                    ->getOptionLabelFromRecordUsing(
                        fn (Player $record): string =>
                            $this->playerLabel($record)
                    )
                    ->searchable(['name', 'nickname', 'city'])
                    ->preload()
                    ->helperText(
                        'Cari pemain dahulu. Klik + jika pemain belum terdaftar.'
                    )
                    ->createOptionForm($this->newPlayerFields())
                    ->createOptionAction(
                        fn (Action $action): Action => $action
                            ->modalHeading('Tambah Pemain Baru')
                            ->modalSubmitActionLabel('Simpan Pemain')
                            ->authorize(
                                fn (): bool => PlayerResource::canCreate()
                            )
                    )
                    ->different('player_one_id')
                    ->validationMessages([
                        'different' => 'Pemain kedua harus berbeda dari pemain pertama.',
                    ])
                    ->required(),
            ]);
    }

    protected function newPlayerFields(): array
    {
        return [
            TextInput::make('name')
                ->label('Nama Lengkap')
                ->required()
                ->maxLength(255)
                ->helperText(
                    'Pastikan orang ini belum terdaftar agar riwayatnya tidak terpisah.'
                ),

            TextInput::make('nickname')
                ->label('Nama Panggilan')
                ->maxLength(100),

            TextInput::make('city')
                ->label('Kota / Domisili')
                ->placeholder('Contoh: Cianjur')
                ->maxLength(100),

            TextInput::make('phone')
                ->label('Nomor WhatsApp')
                ->tel()
                ->maxLength(30)
                ->helperText('Opsional. Untuk kebutuhan internal panitia.'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Pasangan dan Pembagian Grup')
            ->description(fn (): string => $this->groupReadinessSummary())
            ->recordTitleAttribute('id')
            ->modelLabel('Pasangan')
            ->pluralModelLabel('Pasangan')
            ->columns([
                TextColumn::make('id')
                    ->label('ID Pasangan')
                    ->sortable(),

                TextColumn::make('playerOne.name')
                    ->label('Pemain Pertama')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('playerTwo.name')
                    ->label('Pemain Kedua')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('group.name')
                    ->label('Grup')
                    ->badge()
                    ->placeholder('Belum ditempatkan'),
            ])
            ->headerActions([
                Action::make('createGroups')
                    ->label('Buat Grup')
                    ->icon(Heroicon::OutlinedSquares2x2)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Buat Grup Pertandingan')
                    ->modalDescription(
                        'Grup akan dibuat sesuai pengaturan kategori. '
                        . 'Pasangan belum akan ditempatkan ke grup.'
                    )
                    ->modalSubmitActionLabel('Buat Grup')
                    ->action(fn () => $this->createGroups()),

                CreateAction::make()
                    ->label('Tambah Pasangan')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->modalHeading('Daftarkan Pasangan')
                    ->successNotificationTitle('Pasangan berhasil didaftarkan')
                    ->using(
                        fn (array $data): TournamentPair =>
                            $this->registerPair($data)
                    ),
            ])
            ->recordActions([
                Action::make('assignGroup')
                    ->label('Atur Grup')
                    ->icon(Heroicon::OutlinedSquares2x2)
                    ->modalHeading('Tempatkan Pasangan ke Grup')
                    ->modalSubmitActionLabel('Simpan Penempatan')
                    ->fillForm(fn (TournamentPair $record): array => [
                        'tournament_group_id' => $record->tournament_group_id,
                    ])
                    ->schema([
                        Select::make('tournament_group_id')
                            ->label('Grup')
                            ->options(
                                fn (): array => $this->getOwnerRecord()
                                    ->groups()
                                    ->orderBy('sort_order')
                                    ->pluck('name', 'id')
                                    ->all()
                            )
                            ->helperText(
                                'Pilihan hanya menampilkan grup dari kategori ini. '
                                . 'Jika kosong, klik Buat Grup terlebih dahulu.'
                            )
                            ->native(false)
                            ->required(),
                    ])
                    ->action(function (
                        TournamentPair $record,
                        array $data,
                    ): void {
                        $this->assignPairToGroup($record, $data);
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('id')
            ->emptyStateHeading('Belum ada pasangan')
            ->emptyStateDescription(
                'Pilih dua pemain dari Master Data untuk mendaftarkan pasangan.'
            )
            ->emptyStateIcon(Heroicon::OutlinedUsers);
    }

    protected function playerLabel(Player $player): string
    {
        $code = 'PLY-' . str_pad(
            (string) $player->id,
            5,
            '0',
            STR_PAD_LEFT
        );

        $city = $player->city ?: 'Kota belum diisi';

        return "{$player->name} — {$city} — {$code}";
    }

    protected function registerPair(array $data): TournamentPair
    {
        return DB::transaction(function () use ($data): TournamentPair {
            $category = EventCategory::query()
                ->lockForUpdate()
                ->findOrFail($this->getOwnerRecord()->getKey());

            if ($category->event->status !== 'draft') {
                $this->rejectRegistration(
                    'Pendaftaran pasangan hanya tersedia saat event berstatus Draft.'
                );
            }

            if (
                ! $category->pair_count ||
                ! $category->group_count ||
                ! $category->qualifiers_per_group
            ) {
                $this->rejectRegistration(
                    'Lengkapi pengaturan format kategori sebelum mendaftarkan pasangan.'
                );
            }

            $playerOneId = (int) $data['player_one_id'];
            $playerTwoId = (int) $data['player_two_id'];

            if ($playerOneId === $playerTwoId) {
                $this->rejectRegistration(
                    'Pemain pertama dan kedua harus berbeda.'
                );
            }

            $playerIds = [$playerOneId, $playerTwoId];

            if (Player::whereIn('id', $playerIds)->count() !== 2) {
                $this->rejectRegistration(
                    'Pemain tidak ditemukan. Refresh halaman dan pilih kembali.'
                );
            }

            $pairs = $category->pairs()
                ->lockForUpdate()
                ->get();

            $playerAlreadyRegistered = $pairs->contains(
                fn (TournamentPair $pair): bool =>
                    in_array($pair->player_one_id, $playerIds, true) ||
                    in_array($pair->player_two_id, $playerIds, true)
            );

            if ($playerAlreadyRegistered) {
                $this->rejectRegistration(
                    'Salah satu pemain sudah terdaftar dalam kategori ini.'
                );
            }

            if ($pairs->count() >= $category->pair_count) {
                $this->rejectRegistration(
                    'Kapasitas pasangan pada kategori ini sudah penuh.'
                );
            }

            return $category->pairs()->create([
                'player_one_id' => $playerOneId,
                'player_two_id' => $playerTwoId,
            ]);
        });
    }

    protected function rejectRegistration(string $message): never
    {
        Notification::make()
            ->title('Pasangan belum dapat didaftarkan')
            ->body($message)
            ->danger()
            ->send();

        throw new Halt();
    }

    protected function createGroups(): void
    {
        $groupCount = DB::transaction(function (): int {
            $category = EventCategory::query()
                ->lockForUpdate()
                ->findOrFail($this->getOwnerRecord()->getKey());

            if ($category->event->status !== 'draft') {
                $this->rejectGroupCreation(
                    'Grup hanya dapat dibuat saat event berstatus Draft.'
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
                $this->rejectGroupCreation(
                    'Lengkapi format pertandingan pada kategori terlebih dahulu.'
                );
            }

            if (
                $pairCount % $groupCount !== 0 ||
                intdiv($pairCount, $groupCount) < 2
            ) {
                $this->rejectGroupCreation(
                    'Pasangan harus terbagi rata, minimal 2 pasangan per grup.'
                );
            }

            $totalQualifiers = $groupCount * $qualifiers;

            if (
                ! in_array($totalQualifiers, [2, 4, 8, 16], true) ||
                $totalQualifiers > $pairCount
            ) {
                $this->rejectGroupCreation(
                    'Jumlah pasangan lolos tidak sesuai dengan format fase gugur.'
                );
            }

            if (
                $category->has_third_place_match &&
                $totalQualifiers < 4
            ) {
                $this->rejectGroupCreation(
                    'Perebutan juara 3 memerlukan fase semifinal.'
                );
            }

            $existingGroups = $category->groups()
                ->lockForUpdate()
                ->get();

            if ($existingGroups->isNotEmpty()) {
                $this->rejectGroupCreation(
                    'Kategori ini sudah memiliki grup. Grup yang ada tidak ditimpa.'
                );
            }

            for ($number = 1; $number <= $groupCount; $number++) {
                $category->groups()->create([
                    'name' => 'Grup ' . chr(64 + $number),
                    'sort_order' => $number,
                ]);
            }

            return $groupCount;
        });

        Notification::make()
            ->title('Grup berhasil dibuat')
            ->body(
                "{$groupCount} grup telah dibuat. "
                . 'Pasangan belum ditempatkan ke grup.'
            )
            ->success()
            ->send();
    }

    protected function rejectGroupCreation(string $message): never
    {
        Notification::make()
            ->title('Grup belum dapat dibuat')
            ->body($message)
            ->danger()
            ->send();

        throw new Halt();
    }

    protected function assignPairToGroup(
        TournamentPair $record,
        array $data,
    ): void {
        $groupName = DB::transaction(function () use ($record, $data): string {
            $category = EventCategory::query()
                ->lockForUpdate()
                ->findOrFail($this->getOwnerRecord()->getKey());

            if ($category->event->status !== 'draft') {
                $this->rejectGroupAssignment(
                    'Penempatan grup hanya dapat diubah saat event berstatus Draft.'
                );
            }

            $pairCount = (int) $category->pair_count;
            $groupCount = (int) $category->group_count;

            if (
                ! in_array($pairCount, [8, 16], true) ||
                ! in_array($groupCount, [1, 2, 4, 8], true)
            ) {
                $this->rejectGroupAssignment(
                    'Lengkapi pengaturan jumlah pasangan dan grup terlebih dahulu.'
                );
            }

            if (
                $pairCount % $groupCount !== 0 ||
                intdiv($pairCount, $groupCount) < 2
            ) {
                $this->rejectGroupAssignment(
                    'Format grup tidak valid. Periksa pengaturan kategori.'
                );
            }

            $groups = $category->groups()
                ->lockForUpdate()
                ->get();

            if ($groups->count() !== $groupCount) {
                $this->rejectGroupAssignment(
                    'Jumlah grup yang tersedia tidak sesuai dengan pengaturan kategori.'
                );
            }

            $targetGroupId = (int) $data['tournament_group_id'];

            $targetGroup = $groups->first(
                fn ($group): bool => (int) $group->id === $targetGroupId
            );

            if (! $targetGroup) {
                $this->rejectGroupAssignment(
                    'Grup tidak ditemukan dalam kategori ini.'
                );
            }

            $pairs = $category->pairs()
                ->lockForUpdate()
                ->get();

            $pair = $pairs->first(
                fn (TournamentPair $item): bool =>
                    (int) $item->id === (int) $record->id
            );

            if (! $pair) {
                $this->rejectGroupAssignment(
                    'Pasangan tidak ditemukan dalam kategori ini.'
                );
            }

            if ($pairs->count() > $pairCount) {
                $this->rejectGroupAssignment(
                    'Jumlah pasangan terdaftar melebihi kapasitas kategori. '
                    . 'Periksa kembali pengaturan format.'
                );
            }

            $capacity = intdiv($pairCount, $groupCount);

            $occupied = $pairs->filter(
                fn (TournamentPair $item): bool =>
                    $item->tournament_group_id === $targetGroupId &&
                    (int) $item->id !== (int) $pair->id
            )->count();

            if ($occupied >= $capacity) {
                $this->rejectGroupAssignment(
                    "{$targetGroup->name} sudah penuh. "
                    . "Kapasitas maksimal {$capacity} pasangan."
                );
            }

            $pair->group()->associate($targetGroup);
            $pair->save();

            return $targetGroup->name;
        });

        Notification::make()
            ->title('Penempatan grup berhasil disimpan')
            ->body("Pasangan ditempatkan ke {$groupName}.")
            ->success()
            ->send();
    }

    protected function rejectGroupAssignment(string $message): never
    {
        Notification::make()
            ->title('Penempatan grup belum dapat disimpan')
            ->body($message)
            ->danger()
            ->send();

        throw new Halt();
    }

    protected function groupReadinessSummary(): string
    {
        $category = EventCategory::query()
            ->findOrFail($this->getOwnerRecord()->getKey());

        $pairs = $category->pairs()->get();
        $groups = $category->groups()
            ->orderBy('sort_order')
            ->get();

        $pairCount = (int) $category->pair_count;
        $groupCount = (int) $category->group_count;

        $registered = $pairs->count();

        $unassigned = $pairs
            ->whereNull('tournament_group_id')
            ->count();

        $parts = [
            "Terdaftar: {$registered}/{$pairCount} pasangan",
            "Belum ditempatkan: {$unassigned}",
        ];

        if (
            ! in_array($pairCount, [8, 16], true) ||
            ! in_array($groupCount, [1, 2, 4, 8], true)
        ) {
            $parts[] = 'Lengkapi pengaturan format kategori.';

            return implode(' | ', $parts);
        }

        if (
            $pairCount % $groupCount !== 0 ||
            intdiv($pairCount, $groupCount) < 2
        ) {
            $parts[] = 'Format pembagian grup tidak valid.';

            return implode(' | ', $parts);
        }

        $capacity = intdiv($pairCount, $groupCount);

        $validGroupIds = $groups
            ->map(fn ($group): int => (int) $group->id)
            ->all();

        $invalidAssignments = $pairs->filter(
            fn (TournamentPair $pair): bool =>
                $pair->tournament_group_id !== null &&
                ! in_array(
                    $pair->tournament_group_id,
                    $validGroupIds,
                    true
                )
        )->count();

        $allGroupsFull = $groups->count() === $groupCount;

        foreach ($groups as $group) {
            $occupied = $pairs->filter(
                fn (TournamentPair $pair): bool =>
                    $pair->tournament_group_id === (int) $group->id
            )->count();

            $parts[] = "{$group->name}: {$occupied}/{$capacity}";

            if ($occupied !== $capacity) {
                $allGroupsFull = false;
            }
        }

        if ($groups->count() !== $groupCount) {
            $parts[] = "Grup tersedia: {$groups->count()}/{$groupCount}";
        }

        if ($invalidAssignments > 0) {
            $parts[] = "{$invalidAssignments} pasangan memiliki grup tidak sesuai kategori";
        }

        $complete = $registered === $pairCount
            && $unassigned === 0
            && $invalidAssignments === 0
            && $allGroupsFull;

        $parts[] = $complete
            ? 'Pembagian grup lengkap'
            : 'Pembagian grup belum lengkap';

        return implode(' | ', $parts);
    }
}