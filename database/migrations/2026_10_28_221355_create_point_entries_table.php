<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Points ka ledger: har entry ek wajah ke saath (+10 on-time, +5 first approve, -5 late)
        Schema::create('point_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('content_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason', 30); // on_time, late, first_approve
            $table->integer('points');
            $table->timestamps();
            // Ek hi wajah se do baar points na milein
            $table->unique(['task_id', 'reason']);
            $table->unique(['content_id', 'reason']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_entries');
    }
};
