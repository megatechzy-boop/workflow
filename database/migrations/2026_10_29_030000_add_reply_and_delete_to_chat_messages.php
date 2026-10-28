<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->foreignId('reply_to_id')->nullable()->after('user_id')->constrained('chat_messages')->nullOnDelete();
            $table->timestamp('deleted_at')->nullable(); // "deleted for everyone": row stays as a placeholder
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reply_to_id');
            $table->dropColumn('deleted_at');
        });
    }
};
