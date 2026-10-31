<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Facebook Pages (with their Instagram account) connected by the Admin, and the posts sent to them */
    public function up(): void
    {
        Schema::create('meta_pages', function (Blueprint $table) {
            $table->id();
            $table->string('page_id', 40)->unique();
            $table->string('name');
            $table->text('access_token'); // page token, encrypted
            $table->string('ig_user_id', 40)->nullable();
            $table->string('ig_username')->nullable();
            $table->timestamps();
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('meta_page_id')->nullable()->after('package_start')->constrained('meta_pages')->nullOnDelete();
        });

        Schema::create('meta_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meta_page_id')->constrained('meta_pages')->cascadeOnDelete();
            $table->string('platform', 20); // facebook | instagram
            $table->string('status', 20)->default('queued'); // queued | processing | done | failed
            $table->string('container_id', 60)->nullable(); // Instagram media container
            $table->string('post_id', 80)->nullable();
            $table->text('error')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->foreignId('queued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_posts');
        Schema::table('clients', fn (Blueprint $table) => $table->dropConstrainedForeignId('meta_page_id'));
        Schema::dropIfExists('meta_pages');
    }
};
