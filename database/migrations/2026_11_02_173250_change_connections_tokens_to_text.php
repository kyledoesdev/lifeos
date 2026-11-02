<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bluesky session tokens are JWTs that run past 255 characters.
     */
    public function up(): void
    {
        Schema::table('connections', function (Blueprint $table) {
            $table->text('token')->change();
            $table->text('refresh_token')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('connections', function (Blueprint $table) {
            $table->string('token')->change();
            $table->string('refresh_token')->nullable()->change();
        });
    }
};
