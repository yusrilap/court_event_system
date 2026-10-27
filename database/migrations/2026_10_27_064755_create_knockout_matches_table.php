<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knockout_matches', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_category_id')
                ->constrained('event_categories')
                ->restrictOnDelete();

            $table->enum('round', [
                'round_of_16',
                'quarterfinal',
                'semifinal',
                'final',
                'third_place',
            ]);

            $table->unsignedTinyInteger('position');

            $table->timestamps();

            $table->unique(
                ['event_category_id', 'round', 'position'],
                'knockout_matches_category_round_position_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knockout_matches');
    }
};