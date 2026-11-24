<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\MetaConnection;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Monthly bills (made on the 1st by billing:monthly or the "Create monthly bills" button, not sent yet):
 * package line + last month's Meta ads spend + "Previous balance" of older sent bills still unpaid (those bills
 * become "Moved" when it is sent). The Admin checks / edits a bill and sends it; then the Customer is told and reminders start.
 */
class Billing
{
    /** Reminder days: 2 days before the due date, on it, then every 3 days while overdue */
    public const REMIND_BEFORE_DAYS = 2;

    public const OVERDUE_EVERY_DAYS = 3;

    /** Active clients with a package that has started by the end of this month (no start date = already running) */
    public static function billable(CarbonImmutable $month): Builder
    {
        return Client::where('is_active', true)->whereNotNull('package_id')
            ->where(fn ($q) => $q->whereNull('package_start')->orWhereDate('package_start', '<=', $month->endOfMonth()));
    }

    /** Missing monthly bills of the month; returns how many were made */
    public static function makeMonthly(CarbonImmutable $month): int
    {
        $token = MetaConnection::token();
        $made = 0;
        self::billable($month)->with('package', 'metaAdAccount')->get()->each(function (Client $client) use ($month, $token, &$made) {
            if (Invoice::where('client_id', $client->id)->whereDate('month', $month)->exists()) {
                return;
            }
            self::makeFor($client, $month, $token);
            $made++;
        });

        return $made;
    }

    public static function makeFor(Client $client, CarbonImmutable $month, ?string $token): Invoice
    {
        $items = [['name' => $client->package->name.' package', 'detail' => $month->format('F Y'), 'qty' => 1, 'rate' => $client->package->price, 'kind' => 'package']];

        // Last month's ad spend from the client's ad account (empty when not linked or Meta refuses: add it by hand)
        $spentIn = $month->subMonth();
        if ($token && $client->metaAdAccount) {
            $spend = MetaInsights::accountSpend($token, $client->metaAdAccount->account_id,
                Carbon::parse($spentIn), Carbon::parse($spentIn->endOfMonth()));
            if ($spend >= 1) {
                $items[] = ['name' => 'Meta ads spend', 'detail' => $spentIn->format('F Y'), 'qty' => 1, 'rate' => (int) round($spend), 'kind' => 'ads'];
            }
        }

        // Older bills the Customer has and did not fully pay: their balance moves here
        $older = Invoice::with('payments')->where('client_id', $client->id)->whereNotNull('sent_at')
            ->whereNull('paid_at')->whereNull('carried_to_id')->whereDate('invoice_date', '<', $month)
            ->orderBy('invoice_date')->orderBy('id')->get()->filter(fn (Invoice $i) => $i->due() > 0);
        foreach ($older as $old) {
            $items[] = ['name' => 'Previous balance', 'detail' => $old->number.' ('.($old->month ?? $old->invoice_date)->format('M Y').')',
                'qty' => 1, 'rate' => $old->due(), 'kind' => 'carry', 'from' => $old->id];
        }

        // The older bills become "Moved" only when this one is sent (until then the Customer still sees and pays them)
        return Invoice::create([
            'client_id' => $client->id, 'month' => $month, 'invoice_date' => $month,
            'package_name' => $client->package->name, 'due_date' => $month->day(5), 'items' => $items,
        ]);
    }

    /**
     * Previous balance lines follow their old bill until sending: a payment made meanwhile lowers the line,
     * a bill paid (or moved elsewhere) drops it. Saves the new amount.
     */
    public static function refreshCarries(Invoice $invoice): void
    {
        $olds = Invoice::with('payments')->whereIn('id', collect($invoice->items)->where('kind', 'carry')->pluck('from'))->get()->keyBy('id');
        $invoice->items = collect($invoice->items)->map(function ($item) use ($olds, $invoice) {
            if (($item['kind'] ?? null) !== 'carry') {
                return $item;
            }
            $old = $olds->get($item['from']);
            $open = $old && ! $old->paid_at && in_array($old->carried_to_id, [null, $invoice->id], true) ? $old->due() : 0;

            return $open > 0 ? ['rate' => $open] + $item : null;
        })->filter()->values()->all();
        $invoice->amount = $invoice->subtotal() + $invoice->gstAmount();
        $invoice->save();
    }

    /** Show the bill to the brand's Customers and tell them (bell, push, email); its previous balances move here now */
    public static function send(Invoice $invoice): void
    {
        if ($invoice->sent_at) {
            return;
        }
        DB::transaction(function () use ($invoice) {
            self::refreshCarries($invoice);
            Invoice::whereIn('id', collect($invoice->items)->where('kind', 'carry')->pluck('from'))->update(['carried_to_id' => $invoice->id]);
            $invoice->update(['sent_at' => now()]);
        });
        Notify::send(self::customers($invoice), 'New bill '.$invoice->number,
            '₹'.number_format($invoice->amount).' for '.($invoice->month ?? $invoice->invoice_date)->format('F Y').', due '.$invoice->due_date->format('d M').'. Pay by UPI or bank transfer.',
            route('my.invoice', $invoice));
    }

    /** Daily: reminders for sent, unpaid bills (not moved ones); returns how many bills got one */
    public static function remind(): int
    {
        $today = today();
        $bills = Invoice::with('payments', 'client')->whereNotNull('sent_at')->whereNull('paid_at')->whereNull('carried_to_id')->get()
            ->filter(function (Invoice $i) use ($today) {
                if ($i->due() <= 0) {
                    return false;
                }
                $late = (int) $i->due_date->diffInDays($today, false); // < 0 = days left
                return $late === -self::REMIND_BEFORE_DAYS || $late === 0 || ($late > 0 && $late % self::OVERDUE_EVERY_DAYS === 0);
            });

        foreach ($bills as $bill) {
            $late = (int) $bill->due_date->diffInDays($today, false);
            $when = match (true) {
                $late < 0 => 'is due on '.$bill->due_date->format('d M'),
                $late === 0 => 'is due today',
                default => 'was due on '.$bill->due_date->format('d M').' ('.$late.' '.str('day')->plural($late).' ago)',
            };
            Notify::send(self::customers($bill), 'Payment reminder: '.$bill->number,
                '₹'.number_format($bill->due())." $when. Please ignore this if you have already paid.", route('my.invoice', $bill));
        }

        if ($bills->isNotEmpty()) {
            $overdue = $bills->filter(fn (Invoice $i) => $i->due_date->lt($today));
            Notify::send(User::where('role', Role::Admin)->get(), 'Payment reminders sent',
                $bills->count().' '.str('bill')->plural($bills->count()).', ₹'.number_format($bills->sum(fn ($i) => $i->due())).' to receive'
                .($overdue->isNotEmpty() ? '; '.$overdue->count().' overdue: '.$overdue->map(fn ($i) => $i->client->name)->implode(', ') : '').'.',
                route('admin.billing.index'));
        }

        return $bills->count();
    }

    private static function customers(Invoice $invoice)
    {
        return User::where('role', Role::Client)->where('client_id', $invoice->client_id)->get();
    }
}
