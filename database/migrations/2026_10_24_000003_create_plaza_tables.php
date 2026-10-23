<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plaza_rooms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('topic');
            $table->string('vibe')->default('Chill');
            $table->text('description')->nullable();
            $table->foreignId('host_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('capacity')->default(8); // FR-31: Max 8 participants
            $table->unsignedTinyInteger('current_participants')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('plaza_room_members', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('plaza_room_id')->constrained('plaza_rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->default('listener'); // host, speaker, listener
            $table->boolean('is_muted')->default(false);
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->index(['plaza_room_id', 'user_id']);
        });

        Schema::create('plaza_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('plaza_room_id')->constrained('plaza_rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['plaza_room_id', 'created_at']);
        });

        Schema::create('daily_prompts', function (Blueprint $table) {
            $table->id();
            $table->string('prompt');
            $table->date('date')->unique();
            $table->unsignedInteger('responses_count')->default(0);
            $table->timestamps();
        });

        Schema::create('daily_prompt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_prompt_id')->constrained('daily_prompts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('answer');
            $table->unsignedTinyInteger('mood_score')->default(3); // 1-5 mood rating
            $table->timestamps();

            $table->index(['daily_prompt_id', 'created_at']);
        });

        Schema::create('plaza_events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('scheduled_at');
            $table->string('vibe')->default('Chill');
            $table->string('host_name')->default('Community Host');
            $table->unsignedInteger('attendees_count')->default(0);
            $table->timestamps();
        });

        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('description');
            $table->string('icon')->default('sparkles');
            $table->string('category')->default('karma');
            $table->timestamps();
        });

        Schema::create('user_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('badge_id')->constrained('badges')->cascadeOnDelete();
            $table->timestamp('awarded_at')->useCurrent();
            $table->timestamps();

            $table->unique(['user_id', 'badge_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_badges');
        Schema::dropIfExists('badges');
        Schema::dropIfExists('plaza_events');
        Schema::dropIfExists('daily_prompt_answers');
        Schema::dropIfExists('daily_prompts');
        Schema::dropIfExists('plaza_messages');
        Schema::dropIfExists('plaza_room_members');
        Schema::dropIfExists('plaza_rooms');
    }
};
