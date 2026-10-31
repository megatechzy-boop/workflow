<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Comments on posts we published (answered from the app) and leads from clients' Facebook lead forms */
    public function up(): void
    {
        Schema::create('meta_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meta_post_id')->constrained('meta_posts')->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('platform', 20);
            $table->string('comment_id', 80)->unique();
            $table->string('author')->nullable();
            $table->text('message');
            $table->timestamp('commented_at')->nullable();
            $table->text('reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->foreignId('replied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('meta_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meta_page_id')->constrained('meta_pages')->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('lead_id', 40)->unique();
            $table->string('form_name')->nullable();
            $table->string('name')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->json('fields');
            $table->timestamp('lead_at')->nullable();
            $table->string('status', 20)->default('new'); // new | contacted | won | lost
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_leads');
        Schema::dropIfExists('meta_comments');
    }
};
