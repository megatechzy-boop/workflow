<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Old roles to new team roles: Approver => SMM, Team Member => Graphic Designer */
    public function up(): void
    {
        DB::table('users')->where('role', 'approver')->update(['role' => 'social_media_manager']);
        DB::table('users')->where('role', 'team_member')->update(['role' => 'graphic_designer']);
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'social_media_manager')->update(['role' => 'approver']);
        DB::table('users')->whereIn('role', ['graphic_designer', 'video_editor'])->update(['role' => 'team_member']);
    }
};
