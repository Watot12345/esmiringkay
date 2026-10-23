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
        // 1. Update users table with Emiringkay attributes
        Schema::table('users', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('name');
            $table->string('phone')->nullable()->after('email');
            $table->boolean('birthdate_confirmed')->default(false)->after('password');
            $table->timestamp('verified_at')->nullable()->after('birthdate_confirmed');
            $table->unsignedTinyInteger('trust_score')->default(50)->after('verified_at');
            $table->string('status', 32)->default('active')->after('trust_score');
        });

        // 2. Profiles table
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('avatar')->nullable();
            $table->text('bio')->nullable();
            $table->json('languages')->nullable();
            $table->json('interests')->nullable();
            $table->timestamps();
        });

        // 3. Rooms table
        Schema::create('rooms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 20)->default('text'); // text, video, mask
            $table->string('vibe', 30)->default('Chill'); // Chill, Vent, Play, Spark, Debate
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->string('end_reason', 50)->nullable(); // skip, leave, report, timeout
            $table->timestamps();
        });

        // 4. Room Participants
        Schema::create('room_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('left_at')->nullable();
            $table->boolean('skipped')->default(false);
            $table->timestamps();

            $table->unique(['room_id', 'user_id']);
        });

        // 5. Messages (Auto-purged after 24h per 03-system-requirements.md)
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['room_id', 'created_at']);
        });

        // 6. Connections (Keeps: friend, rival, spark, confidant per 01-context.md)
        Schema::create('connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_a')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_b')->constrained('users')->cascadeOnDelete();
            $table->string('type', 30); // friend, rival, spark, confidant
            $table->string('status', 30)->default('pending'); // pending, mutual, rejected, blocked
            $table->timestamps();

            $table->unique(['user_a', 'user_b']);
        });

        // 7. Direct Messages between Connections
        Schema::create('direct_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('connections')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        // 8. Reports (Safety per 01-context.md & 04-architecture.md)
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reported_id')->constrained('users')->cascadeOnDelete();
            $table->string('category', 60);
            $table->text('note')->nullable();
            $table->string('status', 30)->default('pending'); // pending, reviewed, dismissed, sanctioned
            $table->string('severity', 20)->default('medium'); // low, medium, high, critical
            $table->timestamps();
        });

        // 9. Sanctions (Warn, mute, temp ban, perm ban per 03-system-requirements.md)
        Schema::create('sanctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 30); // warn, mute, temp_ban, perm_ban, device_ban
            $table->text('reason');
            $table->timestamp('expires_at')->nullable();
            $table->string('appeal_status', 30)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sanctions');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('direct_messages');
        Schema::dropIfExists('connections');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('room_participants');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('profiles');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'display_name',
                'phone',
                'birthdate_confirmed',
                'verified_at',
                'trust_score',
                'status',
            ]);
        });
    }
};
