<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Brand library: customer/team photos and videos; optionally linked to a calendar row
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('calendar_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->timestamps();
            $table->index(['client_id', 'created_at']);
        });

        // Chat: room = "team" | "client:{id}" | "internal:{id}"
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->string('room', 40)->index();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_mime', 100)->nullable();
            $table->timestamps();
            $table->index(['room', 'id']);
        });

        // For unread counts: how far each user has read in each room
        Schema::create('chat_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('room', 40);
            $table->unsignedBigInteger('last_read_id')->default(0);
            $table->unique(['user_id', 'room']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_reads');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('assets');
    }
};
