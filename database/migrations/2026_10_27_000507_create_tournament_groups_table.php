<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_groups', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_category_id')
                ->constrained('event_categories')
                ->restrictOnDelete();

            $table->string('name', 50);
            $table->unsignedTinyInteger('sort_order');

            $table->timestamps();

            $table->unique(
                ['event_category_id', 'name'],
                'tournament_groups_category_name_unique'
            );

            $table->unique(
                ['event_category_id', 'sort_order'],
                'tournament_groups_category_order_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_groups');
    }
};