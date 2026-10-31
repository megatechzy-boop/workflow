<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Saved Meta audiences, each client's pixel, and an ad's audience + A/B version B */
    public function up(): void
    {
        Schema::create('meta_audiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meta_ad_account_id')->constrained('meta_ad_accounts')->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 20); // page | instagram | website | list | lookalike
            $table->unsignedSmallInteger('days')->nullable();
            $table->unsignedTinyInteger('lookalike_percent')->nullable();
            $table->foreignId('source_id')->nullable()->constrained('meta_audiences')->nullOnDelete();
            $table->unsignedInteger('list_count')->nullable();
            $table->string('audience_id', 40)->nullable();
            $table->string('status', 20)->default('ready'); // ready | failed
            $table->text('error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->string('meta_pixel_id', 40)->nullable();
        });

        Schema::table('meta_ads', function (Blueprint $table) {
            $table->foreignId('meta_audience_id')->nullable()->constrained('meta_audiences')->nullOnDelete();
            $table->string('image_b_path')->nullable();
            $table->text('primary_text_b')->nullable();
            $table->string('headline_b')->nullable();
            $table->string('adset_b_id', 40)->nullable();
            $table->string('ad_b_id', 40)->nullable();
            $table->string('study_id', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('meta_ads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('meta_audience_id');
            $table->dropColumn(['image_b_path', 'primary_text_b', 'headline_b', 'adset_b_id', 'ad_b_id', 'study_id']);
        });
        Schema::table('clients', fn (Blueprint $table) => $table->dropColumn('meta_pixel_id'));
        Schema::dropIfExists('meta_audiences');
    }
};
