<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_court', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')
                ->constrained('events')
                ->restrictOnDelete();

            $table->foreignId('court_id')
                ->constrained('courts')
                ->restrictOnDelete();

            $table->timestamps();

            $table->unique(
                ['event_id', 'court_id'],
                'event_court_event_court_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_court');
    }
};