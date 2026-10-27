<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->time('schedule_start_time')->nullable();

            $table->unsignedSmallInteger('court_gap_minutes')
                ->nullable();

            $table->unsignedSmallInteger('player_rest_minutes')
                ->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'schedule_start_time',
                'court_gap_minutes',
                'player_rest_minutes',
            ]);
        });
    }
};