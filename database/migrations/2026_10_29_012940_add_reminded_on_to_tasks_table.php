<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Last day a deadline reminder was sent, so a task is reminded at most once a day */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->date('reminded_on')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('reminded_on');
        });
    }
};
