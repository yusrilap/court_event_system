<?php

namespace App\Filament\Resources\Events\RelationManagers;

use App\Filament\Resources\Events\EventResource;
use App\Models\Court;
use App\Models\Event;
use App\Models\Location;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class CourtsRelationManager extends RelationManager
{
    protected static string $relationship = 'courts';

    protected static ?string $title = 'Court Event';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->heading('Court yang Digunakan')
            ->description(
                'Pilih court untuk event ini. Pemilihan belum merupakan reservasi waktu.'
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Court')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('location.name')
                    ->label('Lokasi')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('sport')
                    ->label('Olahraga')
                    ->badge()
                    ->formatStateUsing(
                        fn (string $state): string => match ($state) {
                            'padel' => 'Padel',
                            'tennis' => 'Tenis',
                            default => $state,
                        }
                    ),

                IconColumn::make('is_active')
                    ->label('Court Aktif')
                    ->boolean(),

                IconColumn::make('location.is_active')
                    ->label('Lokasi Aktif')
                    ->boolean(),
            ])
            ->headerActions([
                Action::make('addCourt')
                    ->label('Tambah Court')
                    ->icon(Heroicon::OutlinedPlus)
                    ->modalHeading('Pilih Court untuk Event')
                    ->modalSubmitActionLabel('Tambahkan')
                    ->authorize(
                        fn (): bool => EventResource::canEdit(
                            $this->getOwnerRecord()
                        )
                    )
                    ->schema([
                        Select::make('court_id')
                            ->label('Court')
                            ->options(
                                fn (): array => $this->availableCourts()
                            )
                            ->searchable()
                            ->required()
                            ->helperText(
                                'Hanya court aktif dengan lokasi aktif dan '
                                . 'olahraga sesuai event yang ditampilkan.'
                            ),
                    ])
                    ->action(
                        fn (array $data) => $this->addCourtToEvent($data)
                    ),
            ])
            ->recordActions([])
            ->toolbarActions([])
            ->defaultSort('name')
            ->emptyStateHeading('Belum ada court untuk event ini')
            ->emptyStateDescription(
                'Klik Tambah Court untuk memilih dari Master Data.'
            )
            ->emptyStateIcon(Heroicon::OutlinedSquares2x2);
    }

    protected function availableCourts(): array
    {
        $event = Event::findOrFail(
            $this->getOwnerRecord()->getKey()
        );

        if ($event->status !== 'draft') {
            return [];
        }

        return Court::query()
            ->with('location')
            ->where('is_active', true)
            ->where('sport', $event->sport)
            ->whereHas(
                'location',
                fn ($query) => $query->where('is_active', true)
            )
            ->whereDoesntHave(
                'events',
                fn ($query) => $query->where('events.id', $event->id)
            )
            ->orderBy('location_id')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Court $court): array => [
                $court->id => $court->location->name
                    . ' — ' . $court->name
                    . ' (#' . $court->id . ')',
            ])
            ->all();
    }

    protected function addCourtToEvent(array $data): void
    {
        DB::transaction(function () use ($data): void {

            $lock = DB::table('schedule_locks')
                ->where('name', 'global')
                ->lockForUpdate()
                ->first();


            if ($lock === null) {
                $this->rejectCourtSelection(
                    'Pengunci penjadwalan belum tersedia. Jalankan migration terlebih dahulu.'
                );
            }

            $event = Event::query()
                ->lockForUpdate()
                ->findOrFail($this->getOwnerRecord()->getKey());

            if ($event->hasSchedules()) {
                $this->rejectCourtSelection(
                    'Event sudah memiliki jadwal. Court tambahan tidak dapat '
                    . 'dimasukkan melalui pengaturan awal event.'
                );
            }

            if ($event->status !== 'draft') {
                $this->rejectCourtSelection(
                    'Court hanya dapat ditambahkan saat event berstatus Draft.'
                );
            }

            $court = Court::query()
                ->lockForUpdate()
                ->find($data['court_id']);

            if (! $court) {
                $this->rejectCourtSelection(
                    'Court tidak ditemukan. Pilih kembali court yang tersedia.'
                );
            }

            $location = Location::query()
                ->lockForUpdate()
                ->find($court->location_id);

            if (! $court->is_active || ! $location?->is_active) {
                $this->rejectCourtSelection(
                    'Court dan lokasinya harus aktif.'
                );
            }

            if ($court->sport !== $event->sport) {
                $this->rejectCourtSelection(
                    'Cabang olahraga court tidak sesuai dengan event.'
                );
            }

            $alreadyAdded = DB::table('event_court')
                ->where('event_id', $event->id)
                ->where('court_id', $court->id)
                ->lockForUpdate()
                ->first();

            if ($alreadyAdded) {
                $this->rejectCourtSelection(
                    'Court ini sudah ditambahkan ke event.'
                );
            }

            $event->courts()->attach($court->id);
        });

        Notification::make()
            ->title('Court berhasil ditambahkan')
            ->success()
            ->send();
    }

    protected function rejectCourtSelection(string $message): never
    {
        Notification::make()
            ->title('Court belum dapat ditambahkan')
            ->body($message)
            ->danger()
            ->send();

        throw new Halt();
    }
}