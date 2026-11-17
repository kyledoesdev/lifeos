<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stream_category_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stream_category_rule_id')->constrained()->cascadeOnDelete();
            $table->string('platform');
            $table->text('template');
            $table->timestamps();

            $table->unique(['stream_category_rule_id', 'platform'], 'stream_category_templates_rule_platform_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stream_category_templates');
    }
};
