<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Draft rows may have no date yet; every row needs one before the calendar is submitted */
    public function up(): void
    {
        Schema::table('calendar_items', fn (Blueprint $table) => $table->date('post_date')->nullable()->change());
    }

    public function down(): void
    {
        Schema::table('calendar_items', fn (Blueprint $table) => $table->date('post_date')->nullable(false)->change());
    }
};
