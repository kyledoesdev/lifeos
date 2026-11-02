<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stream_category_rules', function (Blueprint $table) {
            $table->id();
            $table->string('category_id')->unique();
            $table->string('category_name');
            $table->string('box_art_url')->nullable();
            $table->json('platforms');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stream_category_rules');
    }
};
