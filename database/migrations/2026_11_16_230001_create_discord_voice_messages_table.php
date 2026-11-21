<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Only who sent a voice message, when, and how long it ran. Never the audio or any text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discord_voice_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discord_channel_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('message_id')->unique();
            $table->unsignedBigInteger('author_id');
            $table->string('author_username');
            $table->string('author_display_name')->nullable();
            $table->decimal('duration_seconds', 8, 2)->nullable();
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index(['discord_channel_id', 'sent_at'], 'discord_voice_messages_channel_sent_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discord_voice_messages');
    }
};
