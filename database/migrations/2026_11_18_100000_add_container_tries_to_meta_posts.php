<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Instagram containers made for this post; an ERROR container is replaced by a new one (3 in all)
        Schema::table('meta_posts', fn (Blueprint $table) => $table->unsignedTinyInteger('container_tries')->default(0)->after('attempts'));
    }

    public function down(): void
    {
        Schema::table('meta_posts', fn (Blueprint $table) => $table->dropColumn('container_tries'));
    }
};
