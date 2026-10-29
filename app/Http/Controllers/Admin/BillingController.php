<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoice;
use App\Support\Month;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Client bills: one per client per month at its package price, due on the 5th */
class BillingController extends Controller
{
    public function index(Request $request): View
    {
        $month = Month::from($request);
        $invoices = Invoice::with('client')->whereDate('month', $month)->get()->sortBy('client.name');
        $missing = Client::where('is_active', true)->whereNotNull('package_id')
            ->whereNotIn('id', $invoices->pluck('client_id'))->count();

        return view('office.billing', compact('month', 'invoices', 'missing'));
    }

    /** Create the missing bills for this month (existing ones are left as they are) */
    public function generate(Request $request): RedirectResponse
    {
        $month = Month::from($request);
        $made = 0;
        Client::with('package')->where('is_active', true)->whereNotNull('package_id')->get()
            ->each(function (Client $client) use ($month, &$made) {
                $invoice = Invoice::firstOrCreate(['client_id' => $client->id, 'month' => $month], [
                    'package_name' => $client->package->name,
                    'amount' => $client->package->price,
                    'due_date' => $month->day(5),
                ]);
                $made += $invoice->wasRecentlyCreated ? 1 : 0;
            });

        return back()->with('status', "{$made} bill(s) created.");
    }

    public function togglePaid(Invoice $invoice): RedirectResponse
    {
        $invoice->update(['paid_at' => $invoice->paid_at ? null : now()]);

        return back()->with('status', $invoice->client->name.($invoice->paid_at ? ' marked paid.' : ' marked unpaid.'));
    }
}
