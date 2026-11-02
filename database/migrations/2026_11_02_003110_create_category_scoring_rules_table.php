<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_scoring_rules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_category_id')
                ->constrained('event_categories')
                ->restrictOnDelete();

            $table->enum('phase', [
                'group',
                'round_of_16',
                'quarterfinal',
                'semifinal',
                'final',
                'third_place',
            ]);

            $table->enum('format', [
                'race_to',
                'total_of',
            ]);

            $table->unsignedSmallInteger('game_count');

            // Diisi ketika aturan fase dikunci untuk pelaksanaan.
            $table->timestamp('locked_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['event_category_id', 'phase'],
                'category_scoring_rules_category_phase_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_scoring_rules');
    }
};