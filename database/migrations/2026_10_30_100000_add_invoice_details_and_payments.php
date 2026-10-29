<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Invoice numbers and line items (monthly or one-off), client address, payments with receipt numbers */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->text('address')->nullable()->after('phone');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('number', 20)->nullable()->unique()->after('id');
            $table->date('invoice_date')->nullable()->after('client_id');
            $table->json('items')->nullable()->after('package_name');
            $table->decimal('gst_rate', 5, 2)->default(0)->after('items');
            $table->date('month')->nullable()->change(); // null = one-off invoice
            $table->string('package_name')->nullable()->change();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('number', 20)->unique(); // receipt number
            $table->date('date');
            $table->string('method', 30);
            $table->string('deposited_to')->nullable();
            $table->unsignedInteger('amount');
            $table->timestamps();
        });

        // Bills made before this: give them numbers, one line item, and a payment if already paid
        $invoiceNo = (int) config('business.invoice_start');
        $receiptNo = (int) config('business.receipt_start');
        $prefix = config('business.invoice_prefix');
        foreach (DB::table('invoices')->orderBy('id')->get() as $invoice) {
            DB::table('invoices')->where('id', $invoice->id)->update([
                'number' => $prefix.str_pad($invoiceNo++, 5, '0', STR_PAD_LEFT),
                'invoice_date' => $invoice->month,
                'items' => json_encode([[
                    'name' => $invoice->package_name.' package',
                    'detail' => date('F Y', strtotime($invoice->month)),
                    'qty' => 1, 'rate' => $invoice->amount,
                ]]),
            ]);
            if ($invoice->paid_at) {
                DB::table('payments')->insert([
                    'invoice_id' => $invoice->id, 'number' => $prefix.str_pad($receiptNo++, 5, '0', STR_PAD_LEFT),
                    'date' => substr($invoice->paid_at, 0, 10), 'method' => 'Account Transfer', 'amount' => $invoice->amount,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['number']);
            $table->dropColumn(['number', 'invoice_date', 'items', 'gst_rate']);
        });
        Schema::table('clients', fn (Blueprint $table) => $table->dropColumn('address'));
    }
};
