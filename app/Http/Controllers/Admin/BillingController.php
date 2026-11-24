<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\Billing;
use App\Support\Month;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Client bills: monthly (package + last month's ad spend + previous balance, made on the 1st) or one-off.
 * A bill is "Not sent" until the Admin sends it; only then the Customer sees it and gets reminders. Payments give receipts.
 */
class BillingController extends Controller
{
    public function index(Request $request): View
    {
        $month = Month::from($request);
        $invoices = Invoice::with(['client', 'payments', 'carriedTo'])
            ->whereBetween('invoice_date', [$month, $month->endOfMonth()])
            ->orderByDesc('number')->get();
        $missing = Billing::billable($month)
            ->whereNotIn('id', Invoice::whereDate('month', $month)->pluck('client_id'))->count();

        return view('office.billing', compact('month', 'invoices', 'missing'));
    }

    /** Create the missing monthly bills (existing ones are left as they are); they wait for "Send" */
    public function generate(Request $request): RedirectResponse
    {
        $made = Billing::makeMonthly(Month::from($request));

        return back()->with('status', "{$made} bill(s) created. Check them, then send.");
    }

    public function create(): View
    {
        $clients = Client::with('package')->orderBy('name')->get();

        return view('office.invoice-form', [
            'clients' => $clients,
            // Choosing a client fills the first line from its package (current price)
            'packages' => $clients->filter->package->mapWithKeys(fn ($c) => [$c->id => ['name' => $c->package->name, 'price' => $c->package->price]]),
        ]);
    }

    /** One-off invoice (website work, yearly renewals …): saved, or saved and sent */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            ...$this->billRules(),
        ]);
        $items = $this->items($data['items']);
        if (! $items) {
            return back()->withInput()->withErrors(['items' => 'Add at least one item with a rate.']);
        }

        $invoice = Invoice::create(['items' => $items] + $data);
        if ($request->boolean('send')) {
            Billing::send($invoice);
        }

        return redirect()->route('admin.billing.show', $invoice)
            ->with('status', "Invoice {$invoice->number} ".($invoice->sent_at ? 'created and sent.' : 'saved. Send it from Billing.'));
    }

    public function show(Invoice $invoice): View
    {
        return view('office.invoice', ['invoice' => $invoice->load(['client', 'payments', 'carriedTo'])]);
    }

    /** A bill not sent yet: change any line (ad spend, extras, package amount), dates; previous balances can be taken out */
    public function edit(Invoice $invoice): View|RedirectResponse
    {
        if ($invoice->sent_at) {
            return redirect()->route('admin.billing.index', ['month' => $invoice->invoice_date->format('Y-m')])
                ->with('status', "Invoice {$invoice->number} is already sent, so its lines are locked.");
        }

        Billing::refreshCarries($invoice); // a payment on an old bill since: its previous balance line follows

        return view('office.invoice-edit', ['invoice' => $invoice->load('client')]);
    }

    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        abort_if((bool) $invoice->sent_at, 422, 'This bill is already sent.');
        $data = $request->validate([
            ...$this->billRules(),
            'items.*.kind' => ['nullable', Rule::in(['package', 'ads'])],
            'carry' => ['array'],
            'carry.*' => ['integer'],
        ]);
        $kept = collect($invoice->items)->where('kind', 'carry')->whereIn('from', $data['carry'] ?? [])->values();
        $items = [...$this->items($data['items']), ...$kept];
        if (! $items) {
            return back()->withInput()->withErrors(['items' => 'Add at least one item with a rate.']);
        }

        DB::transaction(function () use ($invoice, $data, $items, $kept) {
            // A previous balance taken out: that old bill is open again
            Invoice::where('carried_to_id', $invoice->id)->whereNotIn('id', $kept->pluck('from'))->update(['carried_to_id' => null]);
            $invoice->fill(['invoice_date' => $data['invoice_date'], 'due_date' => $data['due_date'], 'items' => $items]);
            $invoice->amount = $invoice->subtotal() + $invoice->gstAmount();
            $invoice->save();
        });
        if ($request->boolean('send')) {
            Billing::send($invoice);
        }

        return redirect()->route('admin.billing.index', ['month' => $invoice->invoice_date->format('Y-m')])
            ->with('status', "Invoice {$invoice->number} ".($invoice->sent_at ? 'saved and sent.' : 'saved.'));
    }

    public function send(Invoice $invoice): RedirectResponse
    {
        Billing::send($invoice);

        return back()->with('status', "Invoice {$invoice->number} sent to the customer.");
    }

    /** Every bill of the month that is not sent yet */
    public function sendAll(Request $request): RedirectResponse
    {
        $month = Month::from($request);
        $bills = Invoice::whereNull('sent_at')->whereBetween('invoice_date', [$month, $month->endOfMonth()])->get();
        $bills->each(fn (Invoice $i) => Billing::send($i));

        return back()->with('status', $bills->count().' bill(s) sent.');
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        if ($invoice->isMoved()) {
            return back()->with('status', "Its balance is in {$invoice->carriedTo->number}: delete that bill first.");
        }
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
        if ($invoice->isMoved()) {
            return back()->with('status', "This balance moved to {$invoice->carriedTo->number}: record the payment there.");
        }
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
        if ($payment->invoice->isMoved()) {
            return back()->with('status', "This bill's balance is already in {$payment->invoice->carriedTo->number}, so its receipts stay.");
        }
        $payment->delete();

        return back()->with('status', "Receipt {$payment->number} removed.");
    }

    private function billRules(): array
    {
        return [
            'invoice_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:invoice_date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['nullable', 'string', 'max:255'],
            'items.*.detail' => ['nullable', 'string', 'max:255'],
            'items.*.qty' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'items.*.rate' => ['nullable', 'integer', 'min:0', 'max:100000000'],
        ];
    }

    /** Filled lines only (a name and a rate) */
    private function items(array $rows): array
    {
        return collect($rows)->filter(fn ($i) => filled($i['name'] ?? null) && filled($i['rate'] ?? null))
            ->map(fn ($i) => ['name' => $i['name'], 'detail' => $i['detail'] ?? null, 'qty' => (int) ($i['qty'] ?? 1), 'rate' => (int) $i['rate']]
                + (filled($i['kind'] ?? null) ? ['kind' => $i['kind']] : []))
            ->values()->all();
    }
}
