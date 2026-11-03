<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Google login of whoever pressed "Connect Google" (one row) and the Business Profile locations it manages */
    public function up(): void
    {
        Schema::create('google_accounts', function (Blueprint $table) {
            $table->id();
            $table->text('access_token'); // encrypted
            $table->text('refresh_token'); // encrypted
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('gbp_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('account_name', 60); // accounts/123
            $table->string('location_name', 60)->unique(); // locations/456
            $table->string('title');
            $table->string('city')->nullable();
            $table->string('phone', 40)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gbp_locations');
        Schema::dropIfExists('google_accounts');
    }
};
