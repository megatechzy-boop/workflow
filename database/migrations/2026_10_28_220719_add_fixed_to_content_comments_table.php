<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Fixed ✔" checklist: member revision comment ko fixed mark karta hai
        Schema::table('content_comments', function (Blueprint $table) {
            $table->timestamp('fixed_at')->nullable();
            $table->foreignId('fixed_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('content_comments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fixed_by');
            $table->dropColumn('fixed_at');
        });
    }
};
