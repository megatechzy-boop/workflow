<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // GBP post made from an approved calendar post (its own caption; the image is a copy)
        Schema::table('gbp_posts', fn (Blueprint $table) => $table->foreignId('content_id')->nullable()->after('location_id')->constrained()->nullOnDelete());
    }

    public function down(): void
    {
        Schema::table('gbp_posts', fn (Blueprint $table) => $table->dropConstrainedForeignId('content_id'));
    }
};
