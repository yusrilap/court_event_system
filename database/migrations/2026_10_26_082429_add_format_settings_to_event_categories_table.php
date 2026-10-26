<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_categories', function (Blueprint $table) {
            $table->unsignedTinyInteger('pair_count')->nullable();

            $table->unsignedTinyInteger('group_count')->nullable();

            $table->unsignedTinyInteger('qualifiers_per_group')->nullable();

            $table->boolean('has_third_place_match')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('event_categories', function (Blueprint $table) {
            $table->dropColumn([
                'pair_count',
                'group_count',
                'qualifiers_per_group',
                'has_third_place_match',
            ]);
        });
    }
};