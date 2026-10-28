<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Customer har row alag check karta hai: approved / revision (+ comment) */
    public function up(): void
    {
        Schema::table('calendar_items', function (Blueprint $table) {
            $table->string('client_status', 20)->nullable();
            $table->text('client_comment')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('calendar_items', function (Blueprint $table) {
            $table->dropColumn(['client_status', 'client_comment']);
        });
    }
};
