<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\Month;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Client bills: monthly at the package price (due on the 5th) or one-off; payments give receipts */
class BillingController extends Controller
{
    public function index(Request $request): View
    {
        $month = Month::from($request);
        $invoices = Invoice::with(['client', 'payments'])
            ->whereBetween('invoice_date', [$month, $month->endOfMonth()])
            ->orderByDesc('number')->get();
        $missing = $this->billable($month)
            ->whereNotIn('id', Invoice::whereDate('month', $month)->pluck('client_id'))->count();

        return view('office.billing', compact('month', 'invoices', 'missing'));
    }

    /** Create the missing monthly bills (existing ones are left as they are) */
    public function generate(Request $request): RedirectResponse
    {
        $month = Month::from($request);
        $made = 0;
        $this->billable($month)->with('package')->get()
            ->each(function (Client $client) use ($month, &$made) {
                if (Invoice::where('client_id', $client->id)->whereDate('month', $month)->exists()) {
                    return;
                }
                Invoice::create([
                    'client_id' => $client->id, 'month' => $month, 'invoice_date' => $month,
                    'package_name' => $client->package->name, 'due_date' => $month->day(5),
                    'items' => [['name' => $client->package->name.' package', 'detail' => $month->format('F Y'), 'qty' => 1, 'rate' => $client->package->price]],
                ]);
                $made++;
            });

        return back()->with('status', "{$made} bill(s) created.");
    }

    /** Active clients with a package that has started by the end of this month (no start date = already running) */
    private function billable(CarbonImmutable $month)
    {
        return Client::where('is_active', true)->whereNotNull('package_id')
            ->where(fn ($q) => $q->whereNull('package_start')->orWhereDate('package_start', '<=', $month->endOfMonth()));
    }

    public function create(): View
    {
        return view('office.invoice-form', ['clients' => Client::orderBy('name')->get()]);
    }

    /** One-off invoice (website work, yearly renewals …) */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:invoice_date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['nullable', 'string', 'max:255'],
            'items.*.detail' => ['nullable', 'string', 'max:255'],
            'items.*.qty' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'items.*.rate' => ['nullable', 'integer', 'min:0', 'max:100000000'],
        ]);
        $items = collect($data['items'])->filter(fn ($i) => filled($i['name'] ?? null) && filled($i['rate'] ?? null))
            ->map(fn ($i) => ['name' => $i['name'], 'detail' => $i['detail'] ?? null, 'qty' => (int) ($i['qty'] ?? 1), 'rate' => (int) $i['rate']])
            ->values()->all();
        if (! $items) {
            return back()->withInput()->withErrors(['items' => 'Add at least one item with a rate.']);
        }

        $invoice = Invoice::create(['items' => $items] + $data);

        return redirect()->route('admin.billing.show', $invoice)->with('status', "Invoice {$invoice->number} created.");
    }

    public function show(Invoice $invoice): View
    {
        return view('office.invoice', ['invoice' => $invoice->load(['client', 'payments'])]);
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        abort_if($invoice->payments()->exists(), 422, 'Remove its payments first.');
        $invoice->delete();

        return back()->with('status', "Invoice {$invoice->number} deleted.");
    }

    /** Fix the amount of a one-item bill (e.g. an old client on a lower price); never below what was already received */
    public function amount(Request $request, Invoice $invoice): RedirectResponse
    {
        abort_unless(count($invoice->items) === 1, 422);
        $rate = (int) $request->validate(['rate' => ['required', 'integer', 'min:'.max(1, $invoice->load('payments')->received()), 'max:100000000']])['rate'];

        $invoice->items = [['rate' => $rate, 'qty' => 1] + $invoice->items[0]];
        $invoice->amount = $invoice->subtotal() + $invoice->gstAmount();
        $invoice->save();
        $invoice->syncPaid();

        return back()->with('status', "Invoice {$invoice->number} is now ₹".number_format($invoice->amount).'.');
    }

    public function pay(Request $request, Invoice $invoice): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:'.max(1, $invoice->load('payments')->due())],
            'date' => ['required', 'date'],
            'method' => ['required', Rule::in(Payment::METHODS)],
            'deposited_to' => ['nullable', 'string', 'max:100'],
        ]);
        $payment = $invoice->payments()->create($data);

        return back()->with('status', "Payment saved. Receipt {$payment->number}.");
    }

    public function receipt(Payment $payment): View
    {
        return view('office.receipt', ['payment' => $payment->load('invoice.client', 'invoice.payments')]);
    }

    public function destroyPayment(Payment $payment): RedirectResponse
    {
        $payment->delete();

        return back()->with('status', "Receipt {$payment->number} removed.");
    }
}
