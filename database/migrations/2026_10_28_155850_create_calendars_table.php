<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->date('month'); // hamesha mahine ki 1 tareekh
            $table->string('status', 30)->default('draft')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['client_id', 'month']);
        });

        Schema::create('calendar_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_id')->constrained()->cascadeOnDelete();
            $table->date('post_date');
            $table->string('post_type', 20);
            $table->text('creative_copy')->nullable();
            $table->text('visual_brief')->nullable();
            $table->text('brief')->nullable();
            $table->string('reference_link', 500)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('calendar_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('action', 20); // submitted, approved, revision
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_reviews');
        Schema::dropIfExists('calendar_items');
        Schema::dropIfExists('calendars');
    }
};
