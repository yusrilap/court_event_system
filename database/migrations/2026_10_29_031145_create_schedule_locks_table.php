<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_locks', function (Blueprint $table) {
            $table->string('name', 50)->primary();
        });

        DB::table('schedule_locks')->insert([
            'name' => 'global',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_locks');
    }
};