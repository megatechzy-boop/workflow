<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Ads made with the Ad Setup form (created paused; "Start" makes them live) */
    public function up(): void
    {
        Schema::create('meta_ads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meta_ad_account_id')->constrained('meta_ad_accounts')->cascadeOnDelete();
            $table->string('name');
            $table->string('goal', 20); // whatsapp | messages | calls | website | awareness
            $table->unsignedInteger('daily_budget'); // rupees
            $table->date('start_on');
            $table->date('end_on');
            $table->string('city')->nullable();
            $table->unsignedSmallInteger('radius_km')->nullable();
            $table->unsignedTinyInteger('age_min');
            $table->unsignedTinyInteger('age_max');
            $table->string('gender', 10)->default('all'); // all | male | female
            $table->string('interests')->nullable(); // comma separated words
            $table->text('primary_text');
            $table->string('headline')->nullable();
            $table->string('url')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('image_path');
            $table->string('status', 20)->default('paused'); // paused | active | failed
            $table->string('campaign_id', 40)->nullable();
            $table->string('adset_id', 40)->nullable();
            $table->string('ad_id', 40)->nullable();
            $table->text('error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ads');
    }
};
