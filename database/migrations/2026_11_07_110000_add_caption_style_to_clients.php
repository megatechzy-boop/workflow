<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Per-client rules for AI captions: tone, length, emojis, fixed CTA / hashtags, sample captions */
    public function up(): void
    {
        Schema::table('clients', fn (Blueprint $table) => $table->text('caption_style')->nullable());
    }

    public function down(): void
    {
        Schema::table('clients', fn (Blueprint $table) => $table->dropColumn('caption_style'));
    }
};
