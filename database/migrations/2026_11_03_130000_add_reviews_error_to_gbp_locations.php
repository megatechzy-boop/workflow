<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Last review sync error, shown on Admin > Google Business */
    public function up(): void
    {
        Schema::table('gbp_locations', function (Blueprint $table) {
            $table->text('reviews_error')->nullable()->after('reviews_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('gbp_locations', fn (Blueprint $table) => $table->dropColumn('reviews_error'));
    }
};
