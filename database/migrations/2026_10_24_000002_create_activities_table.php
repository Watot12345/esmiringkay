<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->string('kind', 40); // doodle, trivia, breathing, music, icebreaker
            $table->json('state')->nullable();
            $table->timestamps();

            $table->index(['room_id', 'kind']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
