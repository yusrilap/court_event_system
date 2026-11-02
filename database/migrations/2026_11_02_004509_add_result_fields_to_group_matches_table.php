<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_matches', function (Blueprint $table) {
            $table->unsignedSmallInteger('score_one')->nullable();
            $table->unsignedSmallInteger('score_two')->nullable();

            // Salinan aturan yang digunakan saat hasil dicatat.
            $table->string('result_scoring_format', 20)->nullable();
            $table->unsignedSmallInteger('result_game_count')->nullable();

            // Waktu pencatatan hasil, bukan waktu selesai aktual.
            $table->timestamp('result_recorded_at')->nullable();

            $table->foreignId('result_recorded_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('group_matches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('result_recorded_by');

            $table->dropColumn([
                'score_one',
                'score_two',
                'result_scoring_format',
                'result_game_count',
                'result_recorded_at',
            ]);
        });
    }
};