<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each channel names the bot that reads it by its key under services.discord in
 * config, so the token itself stays in .env. Only voice messages are saved, so
 * the newest and oldest message ids the sync has read are kept here to know where
 * to pick back up.
 */
return new class extends Migration
{
    /**
     * The Royal Tea voice memo channel, the only one the beans stats page tracks for now.
     */
    private const int VOICE_MEMO_CHANNEL_ID = 1398746079767302224;

    private const string BEANS_BOT = 'royalty.beans';

    public function up(): void
    {
        Schema::create('discord_channels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id')->unique();
            $table->unsignedBigInteger('guild_id')->nullable();
            $table->string('name')->nullable();
            $table->string('bot');
            $table->unsignedBigInteger('newest_message_id')->nullable();
            $table->unsignedBigInteger('oldest_message_id')->nullable();
            $table->timestamp('backfilled_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        DB::table('discord_channels')->insert([
            'channel_id' => self::VOICE_MEMO_CHANNEL_ID,
            'bot' => self::BEANS_BOT,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('discord_channels');
    }
};
