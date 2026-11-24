<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // null = not sent yet: the Customer does not see it and gets no reminders
            $table->timestamp('sent_at')->nullable()->after('paid_at');
            // Its unpaid amount moved into this later bill as "Previous balance"
            $table->foreignId('carried_to_id')->nullable()->after('sent_at')->constrained('invoices')->nullOnDelete();
        });

        // Bills made before this were already shown to the Customer
        DB::table('invoices')->update(['sent_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('carried_to_id');
            $table->dropColumn('sent_at');
        });
    }
};
