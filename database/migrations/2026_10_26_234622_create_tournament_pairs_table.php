<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_pairs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_category_id')
                ->constrained('event_categories')
                ->restrictOnDelete();

            $table->foreignId('player_one_id')
                ->constrained('players')
                ->restrictOnDelete();

            $table->foreignId('player_two_id')
                ->constrained('players')
                ->restrictOnDelete();

            $table->timestamps();

            $table->unique(
                ['event_category_id', 'player_one_id', 'player_two_id'],
                'tournament_pairs_category_players_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_pairs');
    }
};