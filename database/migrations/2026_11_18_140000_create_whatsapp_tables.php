<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One chat per customer number on the Mega Techzy WhatsApp number
        Schema::create('whatsapp_chats', function (Blueprint $table) {
            $table->id();
            $table->string('wa_id', 20)->unique(); // customer number, digits only with country code
            $table->string('name')->nullable();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamp('last_inbound_at')->nullable(); // free replies for 24 hours after this
            $table->unsignedInteger('unread')->default(0);
            $table->timestamps();
        });

        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_chat_id')->constrained()->cascadeOnDelete();
            $table->string('wamid')->nullable()->unique();
            $table->string('direction', 3); // in | out
            $table->string('source', 10)->default('app'); // out: app (our inbox) | phone (WhatsApp app / Web)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20); // text | image | document | template | audio | video | other
            $table->text('body')->nullable();
            $table->string('media_id')->nullable();
            $table->string('media_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('mime', 100)->nullable();
            $table->string('status', 12)->nullable(); // sent | delivered | read | failed
            $table->string('error', 500)->nullable();
            $table->timestamp('sent_at')->index();
            $table->timestamps();
        });

        // Ops Managers get the inbox by default; give it to sales members in Users > Edit
        DB::table('role_permissions')->insertOrIgnore(['role' => 'manager', 'permission' => 'whatsapp_chat']);
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_chats');
        DB::table('role_permissions')->where('permission', 'whatsapp_chat')->delete();
        DB::table('user_permissions')->where('permission', 'whatsapp_chat')->delete();
    }
};
