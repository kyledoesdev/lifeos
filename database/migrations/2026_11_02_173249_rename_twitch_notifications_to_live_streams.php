<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Notifications sent before this migration only recorded when the stream started,
     * so those rows keep their start and notified times and leave the rest empty.
     */
    public function up(): void
    {
        Schema::rename('twitch_notifications', 'live_streams');

        Schema::table('live_streams', function (Blueprint $table) {
            $table->dropUnique('twitch_notifications_stream_started_at_unique');
            $table->renameColumn('notified_at', 'discord_notified_at');
        });

        Schema::table('live_streams', function (Blueprint $table) {
            $table->string('external_id')->nullable()->unique()->after('id');
            $table->string('title')->nullable()->after('external_id');
            $table->string('category_id')->nullable()->after('title');
            $table->string('category_name')->nullable()->after('category_id');
            $table->timestamp('started_at')->nullable()->after('category_name');
            $table->timestamp('ended_at')->nullable()->after('started_at');
        });

        DB::table('live_streams')->orderBy('id')->each(function (object $stream): void {
            DB::table('live_streams')->where('id', $stream->id)->update([
                'started_at' => Carbon::parse($stream->stream_started_at)->setTimezone(config('app.timezone')),
            ]);
        });

        Schema::table('live_streams', function (Blueprint $table) {
            $table->dropColumn('stream_started_at');
        });

        Schema::table('live_streams', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable(false)->change();
            $table->timestamp('discord_notified_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('live_streams', function (Blueprint $table) {
            $table->string('stream_started_at')->nullable()->after('id');
        });

        DB::table('live_streams')->orderBy('id')->each(function (object $stream): void {
            DB::table('live_streams')->where('id', $stream->id)->update([
                'stream_started_at' => Carbon::parse($stream->started_at, config('app.timezone'))->utc()->format('Y-m-d\TH:i:s\Z'),
                'discord_notified_at' => $stream->discord_notified_at ?? $stream->started_at,
            ]);
        });

        Schema::table('live_streams', function (Blueprint $table) {
            $table->dropUnique(['external_id']);
            $table->dropColumn(['external_id', 'title', 'category_id', 'category_name', 'started_at', 'ended_at']);
            $table->renameColumn('discord_notified_at', 'notified_at');
        });

        Schema::table('live_streams', function (Blueprint $table) {
            $table->string('stream_started_at')->nullable(false)->unique('twitch_notifications_stream_started_at_unique')->change();
            $table->timestamp('notified_at')->nullable(false)->change();
        });

        Schema::rename('live_streams', 'twitch_notifications');
    }
};
