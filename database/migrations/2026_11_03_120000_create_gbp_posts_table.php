<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Google Business Profile posts: one at a time, published by gbp:posts when due */
    public function up(): void
    {
        Schema::create('gbp_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('gbp_locations')->cascadeOnDelete();
            $table->string('status', 12)->default('scheduled'); // scheduled | publishing | published | failed
            $table->string('post_type', 10); // update | offer | event
            $table->text('caption');
            $table->string('image_path');
            $table->string('cta_type', 20)->nullable();
            $table->string('cta_url', 500)->nullable();
            $table->string('title')->nullable(); // offer / event title
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('coupon', 60)->nullable();
            $table->text('terms')->nullable();
            $table->timestamp('scheduled_at');
            $table->timestamp('retry_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->string('google_post_name')->nullable();
            $table->string('search_url', 1000)->nullable();
            $table->text('error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gbp_posts');
    }
};
