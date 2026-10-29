<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Module 7: attendance, salary, client billing, expenses */
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('status', 20)->default('present'); // present | half_day | leave
            $table->timestamp('check_in_at')->nullable();
            $table->timestamp('check_out_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'date']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('monthly_salary')->nullable()->after('client_id');
        });

        Schema::create('salary_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('month');
            $table->unsignedInteger('amount');
            $table->timestamp('paid_at');
            $table->timestamps();
            $table->unique(['user_id', 'month']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->date('month');
            $table->string('package_name');
            $table->unsignedInteger('amount');
            $table->date('due_date');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['client_id', 'month']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('title');
            $table->string('category', 30);
            $table->unsignedInteger('amount');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('salary_payments');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('monthly_salary'));
        Schema::dropIfExists('attendances');
    }
};
