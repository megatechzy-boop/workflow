<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ek task ke har post type (jaise linkedin_post, instagram_reel) ka alag content
        Schema::create('contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('post_type', 30);
            $table->foreignId('approver_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->index();
            $table->unsignedInteger('current_version')->default(1);
            $table->timestamps();
            $table->unique(['task_id', 'post_type']);
        });

        // Har upload/resubmit = ek version (V1, V2...)
        Schema::create('content_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->text('caption')->nullable();
            $table->text('hashtags')->nullable();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['content_id', 'number']);
        });

        Schema::create('content_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_version_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10); // image, video, cover
            $table->string('path');
            $table->string('original_name');
            $table->unsignedTinyInteger('sort_order')->default(1);
            $table->timestamps();
        });

        // Comments + actions (approved/revision/rejected...) ek hi timeline mein
        Schema::create('content_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('action', 20)->default('comment');
            $table->text('body')->nullable();
            $table->unsignedTinyInteger('slide')->nullable();
            $table->unsignedInteger('at_seconds')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_comments');
        Schema::dropIfExists('content_media');
        Schema::dropIfExists('content_versions');
        Schema::dropIfExists('contents');
    }
};
