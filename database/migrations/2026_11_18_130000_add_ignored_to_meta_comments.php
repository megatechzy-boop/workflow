<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Ignore": spam or comments that need no answer leave the To reply list (Undo brings them back)
        Schema::table('meta_comments', function (Blueprint $table) {
            $table->timestamp('ignored_at')->nullable()->after('replied_by');
            $table->foreignId('ignored_by')->nullable()->after('ignored_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('meta_comments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ignored_by');
            $table->dropColumn('ignored_at');
        });
    }
};
