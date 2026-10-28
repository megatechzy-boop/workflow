<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Naya step: Ops Manager calendar approve karta hai (Customer se pehle) */
    public function up(): void
    {
        DB::table('role_permissions')->insertOrIgnore(['role' => 'manager', 'permission' => 'approve_calendar']);
    }

    public function down(): void
    {
        DB::table('role_permissions')->where('permission', 'approve_calendar')->delete();
        DB::table('user_permissions')->where('permission', 'approve_calendar')->delete();
    }
};
