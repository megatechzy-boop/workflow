<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Quick post": made by the SMM outside the calendar flow (no designer task, no Customer approval)
        Schema::table('calendar_items', function (Blueprint $table) {
            $table->boolean('is_quick')->default(false)->after('client_comment');
        });
    }

    public function down(): void
    {
        Schema::table('calendar_items', function (Blueprint $table) {
            $table->dropColumn('is_quick');
        });
    }
};
