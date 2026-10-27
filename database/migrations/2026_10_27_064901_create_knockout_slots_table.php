<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knockout_slots', function (Blueprint $table) {
            $table->id();

            $table->foreignId('knockout_match_id')
                ->constrained('knockout_matches')
                ->restrictOnDelete();

            $table->enum('side', ['A', 'B']);

            $table->enum('source_type', [
                'group_rank',
                'match_winner',
                'match_loser',
            ]);

            $table->foreignId('source_group_id')
                ->nullable()
                ->constrained('tournament_groups')
                ->restrictOnDelete();

            $table->unsignedTinyInteger('source_rank')->nullable();

            $table->foreignId('source_match_id')
                ->nullable()
                ->constrained('knockout_matches')
                ->restrictOnDelete();

            $table->timestamps();

            $table->unique(
                ['knockout_match_id', 'side'],
                'knockout_slots_match_side_unique'
            );

            $table->unique(
                ['source_group_id', 'source_rank'],
                'knockout_slots_group_rank_unique'
            );

            $table->unique(
                ['source_match_id', 'source_type'],
                'knockout_slots_match_source_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knockout_slots');
    }
};