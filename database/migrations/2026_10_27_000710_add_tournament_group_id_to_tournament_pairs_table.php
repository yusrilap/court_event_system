<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_pairs', function (Blueprint $table) {
            $table->foreignId('tournament_group_id')
                ->nullable()
                ->constrained('tournament_groups')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tournament_pairs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tournament_group_id');
        });
    }
};