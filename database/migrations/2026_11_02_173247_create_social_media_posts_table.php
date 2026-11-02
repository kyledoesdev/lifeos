<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_media_posts', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->nullable();
            $table->unsignedBigInteger('postable_id');
            $table->string('postable_type');
            $table->string('platform');
            $table->text('body');
            $table->string('status');
            $table->string('url')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->unique(['postable_id', 'postable_type', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_media_posts');
    }
};
