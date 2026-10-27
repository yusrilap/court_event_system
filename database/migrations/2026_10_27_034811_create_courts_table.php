<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('location_id')
                ->constrained('locations')
                ->restrictOnDelete();

            $table->string('name', 100);

            $table->enum('sport', [
                'padel',
                'tennis',
            ]);

            $table->text('notes')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(
                ['location_id', 'name'],
                'courts_location_name_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courts');
    }
};