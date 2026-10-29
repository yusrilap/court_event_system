<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_matches', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tournament_group_id')
                ->constrained('tournament_groups')
                ->restrictOnDelete();

            $table->foreignId('pair_one_id')
                ->constrained('tournament_pairs')
                ->restrictOnDelete();

            $table->foreignId('pair_two_id')
                ->constrained('tournament_pairs')
                ->restrictOnDelete();

            $table->unsignedSmallInteger('round_number');

            $table->enum('status', [
                'pending',
                'completed',
            ])->default('pending');

            $table->timestamps();

            $table->unique(
                ['tournament_group_id', 'pair_one_id', 'pair_two_id'],
                'group_matches_group_pairs_unique'
            );

            $table->index(
                ['tournament_group_id', 'round_number'],
                'group_matches_group_round_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_matches');
    }
};