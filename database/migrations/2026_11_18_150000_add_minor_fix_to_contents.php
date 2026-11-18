<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Send back for changes" on an approved post: the SMM's approval of the fix is final (no Customer step)
        Schema::table('contents', function (Blueprint $table) {
            $table->boolean('minor_fix')->default(false)->after('current_version');
        });
    }

    public function down(): void
    {
        Schema::table('contents', function (Blueprint $table) {
            $table->dropColumn('minor_fix');
        });
    }
};
