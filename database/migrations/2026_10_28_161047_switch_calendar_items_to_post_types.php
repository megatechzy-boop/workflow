<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** platform + post_type (ek) => post_types (list), jaise ["linkedin_post", "instagram_reel"] */
    public function up(): void
    {
        Schema::table('calendar_items', function (Blueprint $table) {
            $table->json('post_types')->nullable()->after('post_date');
        });

        DB::table('calendar_items')->orderBy('id')->each(function ($row) {
            DB::table('calendar_items')->where('id', $row->id)->update([
                'post_types' => json_encode([($row->platform ?: 'instagram').'_'.$row->post_type]),
            ]);
        });

        Schema::table('calendar_items', function (Blueprint $table) {
            $table->dropColumn(['platform', 'post_type']);
        });
    }

    public function down(): void
    {
        Schema::table('calendar_items', function (Blueprint $table) {
            $table->string('platform', 20)->nullable();
            $table->string('post_type', 20)->default('post');
        });

        DB::table('calendar_items')->orderBy('id')->each(function ($row) {
            [$platform, $type] = explode('_', json_decode($row->post_types, true)[0] ?? 'instagram_post');
            DB::table('calendar_items')->where('id', $row->id)->update(['platform' => $platform, 'post_type' => $type]);
        });

        Schema::table('calendar_items', function (Blueprint $table) {
            $table->dropColumn('post_types');
        });
    }
};
