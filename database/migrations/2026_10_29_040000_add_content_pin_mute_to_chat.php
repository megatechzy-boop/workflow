<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->foreignId('content_id')->nullable()->after('reply_to_id')->constrained()->nullOnDelete(); // "Discuss in chat" card
            $table->timestamp('pinned_at')->nullable(); // one pinned message per group
        });
        Schema::table('chat_reads', function (Blueprint $table) {
            $table->boolean('muted')->default(false); // per user per group: no phone push
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('content_id');
            $table->dropColumn('pinned_at');
        });
        Schema::table('chat_reads', function (Blueprint $table) {
            $table->dropColumn('muted');
        });
    }
};
