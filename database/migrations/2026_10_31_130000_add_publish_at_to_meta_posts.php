<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Scheduled posting: meta:publish waits until publish_at (null = right away) */
    public function up(): void
    {
        Schema::table('meta_posts', fn (Blueprint $table) => $table->timestamp('publish_at')->nullable()->after('status'));
    }

    public function down(): void
    {
        Schema::table('meta_posts', fn (Blueprint $table) => $table->dropColumn('publish_at'));
    }
};
