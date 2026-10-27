<?php

namespace App\Filament\Resources\Players\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PlayerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitas Pemain')
                    ->description(
                        'Periksa pemain yang sudah terdaftar sebelum menambahkan data baru.'
                    )
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Lengkap')
                            ->placeholder('Masukkan nama lengkap pemain')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('nickname')
                            ->label('Nama Panggilan')
                            ->placeholder('Opsional')
                            ->maxLength(100),

                        TextInput::make('city')
                            ->label('Kota / Domisili')
                            ->placeholder('Contoh: Cianjur')
                            ->maxLength(100),

                        TextInput::make('phone')
                            ->label('Nomor WhatsApp')
                            ->tel()
                            ->maxLength(30)
                            ->placeholder('Contoh: 081234567890')
                            ->helperText(
                                'Opsional. Digunakan untuk kebutuhan internal panitia.'
                            )
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Catatan Panitia')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Catatan Internal')
                            ->placeholder(
                                'Catatan untuk membantu panitia melakukan kurasi kategori.'
                            )
                            ->helperText(
                                'Catatan ini tidak untuk ditampilkan pada halaman peserta.'
                            )
                            ->rows(4)
                            ->maxLength(10000),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}