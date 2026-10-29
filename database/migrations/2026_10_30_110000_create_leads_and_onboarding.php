<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Website enquiry form => leads; per-client onboarding form (private link) => answers on the client */
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('business')->nullable();
            $table->string('phone', 30);
            $table->string('email')->nullable();
            $table->json('needs');
            $table->text('goal');
            $table->string('budget')->nullable();
            $table->string('timeline')->nullable();
            $table->string('source')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('new'); // new | contacted | won | lost
            $table->text('remarks')->nullable();          // team only
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->string('onboarding_token', 40)->nullable()->unique()->after('package_start');
            $table->json('onboarding')->nullable()->after('onboarding_token');
            $table->timestamp('onboarded_at')->nullable()->after('onboarding');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique(['onboarding_token']);
            $table->dropColumn(['onboarding_token', 'onboarding', 'onboarded_at']);
        });
        Schema::dropIfExists('leads');
    }
};
