<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Google's own "write a review" link of the location (filled by Admin > Google Business > Refresh locations)
        Schema::table('gbp_locations', fn (Blueprint $table) => $table->string('review_url', 500)->nullable()->after('phone'));
    }

    public function down(): void
    {
        Schema::table('gbp_locations', fn (Blueprint $table) => $table->dropColumn('review_url'));
    }
};
