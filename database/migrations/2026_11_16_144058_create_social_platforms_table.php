<?php

use App\Enums\SocialPlatform;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The go live switch and template used to live in the cache forever. This copies
 * whatever is there into the table so posting stays on after deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_platforms', function (Blueprint $table) {
            $table->id();
            $table->string('platform')->unique();
            $table->boolean('enabled')->default(false);
            $table->text('live_stream_template')->nullable();
            $table->timestamps();
        });

        foreach (SocialPlatform::cases() as $platform) {
            DB::table('social_platforms')->insert([
                'platform' => $platform->value,
                'enabled' => (bool) Cache::get("social.{$platform->value}.enabled", false),
                'live_stream_template' => Cache::get("social.{$platform->value}.live_stream_template"),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Cache::forget("social.{$platform->value}.enabled");
            Cache::forget("social.{$platform->value}.live_stream_template");
        }
    }

    public function down(): void
    {
        foreach (DB::table('social_platforms')->get() as $row) {
            Cache::forever("social.{$row->platform}.enabled", (bool) $row->enabled);

            if ($row->live_stream_template !== null) {
                Cache::forever("social.{$row->platform}.live_stream_template", $row->live_stream_template);
            }
        }

        Schema::dropIfExists('social_platforms');
    }
};
