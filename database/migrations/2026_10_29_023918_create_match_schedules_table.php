<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_schedules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('group_match_id')
                ->nullable()
                ->constrained('group_matches')
                ->restrictOnDelete();

            $table->foreignId('knockout_match_id')
                ->nullable()
                ->constrained('knockout_matches')
                ->restrictOnDelete();

            $table->foreignId('court_id')
                ->constrained('courts')
                ->restrictOnDelete();

            $table->dateTime('planned_start_at');
            $table->dateTime('planned_end_at');

            $table->dateTime('actual_start_at')->nullable();
            $table->dateTime('actual_end_at')->nullable();

            $table->timestamps();

            // Satu pertandingan hanya memiliki satu jadwal.
            $table->unique('group_match_id');
            $table->unique('knockout_match_id');

            // Membantu pencarian jadwal berdasarkan court dan waktu.
            $table->index(
                ['court_id', 'planned_start_at'],
                'match_schedules_court_start_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_schedules');
    }
};