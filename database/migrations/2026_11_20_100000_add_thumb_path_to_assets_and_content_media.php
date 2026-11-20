<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Video preview picture (a frame the browser takes once), so the Assets grid shows it instead of loading every video
        foreach (['assets', 'content_media'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('thumb_path')->nullable()->after('path');
            });
        }
    }

    public function down(): void
    {
        foreach (['assets', 'content_media'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('thumb_path');
            });
        }
    }
};
