<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Customers open their own invoices and payment receipts (read-only, with PDF download) */
class CustomerBillController extends Controller
{
    public function invoice(Request $request, Invoice $invoice): View
    {
        $this->authorizeClient($request, $invoice);

        return view('office.invoice', ['invoice' => $invoice->load(['client', 'payments']), 'backUrl' => route('dashboard')]);
    }

    public function receipt(Request $request, Payment $payment): View
    {
        $this->authorizeClient($request, $payment->invoice);

        return view('office.receipt', ['payment' => $payment->load('invoice.client', 'invoice.payments'), 'backUrl' => route('dashboard')]);
    }

    private function authorizeClient(Request $request, Invoice $invoice): void
    {
        $user = $request->user();
        abort_unless($user->client_id !== null && $user->client_id === $invoice->client_id, 403);
    }
}
