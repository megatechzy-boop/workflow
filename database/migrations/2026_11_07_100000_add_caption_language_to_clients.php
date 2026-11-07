<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Language of AI captions (Clients > Edit) */
    public function up(): void
    {
        Schema::table('clients', fn (Blueprint $table) => $table->string('caption_language', 10)->default('en'));
    }

    public function down(): void
    {
        Schema::table('clients', fn (Blueprint $table) => $table->dropColumn('caption_language'));
    }
};
