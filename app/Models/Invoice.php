<?php

namespace App\Models;

use App\Support\DocNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bill for a client: monthly (month set, from its package) or one-off (month null).
 * items: [{name, detail, qty, rate, kind?, from?}], kind: package | ads | carry ("Previous balance" of bill `from`, no GST);
 * amount = total incl. GST; paid_at = when fully paid; sent_at = shown to the Customer (null = not sent yet);
 * carried_to_id = its unpaid amount moved into that later bill (no payments or reminders here any more).
 */
class Invoice extends Model
{
    protected $fillable = ['number', 'client_id', 'invoice_date', 'month', 'package_name', 'items', 'gst_rate', 'amount', 'due_date', 'paid_at', 'sent_at', 'carried_to_id'];

    protected function casts(): array
    {
        return ['month' => 'date', 'invoice_date' => 'date', 'due_date' => 'date', 'paid_at' => 'datetime', 'sent_at' => 'datetime', 'items' => 'array', 'gst_rate' => 'float'];
    }

    protected static function booted(): void
    {
        static::creating(function (Invoice $i) {
            $i->number ??= DocNumber::next('invoices', config('business.invoice_start'));
            $i->invoice_date ??= today();
            $i->gst_rate ??= config('business.gst_rate');
            $i->amount = $i->subtotal() + $i->gstAmount();
        });
        // Deleting a bill gives back the bills whose balance it had taken
        static::deleting(fn (Invoice $i) => Invoice::where('carried_to_id', $i->id)->update(['carried_to_id' => null]));
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('date')->orderBy('id');
    }

    public function carriedTo(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'carried_to_id');
    }

    public function subtotal(): int
    {
        return (int) collect($this->items)->sum(fn ($i) => $i['qty'] * $i['rate']);
    }

    /** GST on the new work only: a previous balance already had its GST */
    public function gstAmount(): int
    {
        $taxable = $this->subtotal() - $this->carriedIn();

        return (int) round($taxable * ($this->gst_rate ?? 0) / 100);
    }

    /** "Previous balance" lines: money of older bills, not new work */
    public function carriedIn(): int
    {
        return (int) collect($this->items)->where('kind', 'carry')->sum(fn ($i) => $i['qty'] * $i['rate']);
    }

    public function received(): int
    {
        return (int) $this->payments->sum('amount');
    }

    public function due(): int
    {
        return max(0, $this->amount - $this->received());
    }

    public function isMoved(): bool
    {
        return $this->carried_to_id !== null;
    }

    /** Still to be paid on this bill: 0 once moved into a later bill; a bill not sent yet leaves out its previous balance (still open on the old bill) */
    public function open(): int
    {
        return match (true) {
            $this->isMoved() => 0,
            ! $this->sent_at => max(0, $this->due() - $this->carriedIn()),
            default => $this->due(),
        };
    }

    /** UPI payment link for the amount still due (shown as a QR on the invoice); null when paid, moved or no UPI ID */
    public function upiLink(): ?string
    {
        $upi = config('business.bank.upi');
        if (! $upi || $this->open() <= 0) {
            return null;
        }

        return 'upi://pay?'.http_build_query([
            'pa' => $upi,
            'pn' => config('business.bank.account_name') ?: config('business.name'),
            'am' => number_format($this->due(), 2, '.', ''),
            'cu' => 'INR',
            'tn' => 'Invoice '.$this->number,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function syncPaid(): void
    {
        $this->load('payments');
        $this->update(['paid_at' => $this->due() === 0 ? ($this->paid_at ?? now()) : null]);
    }

    /** paid | moved | partly | overdue | due */
    public function state(): string
    {
        return match (true) {
            (bool) $this->paid_at => 'paid',
            $this->isMoved() => 'moved',
            $this->due_date->lt(today()) => 'overdue',
            $this->received() > 0 => 'partly',
            default => 'due',
        };
    }

    /** Badge text and colour for a state */
    public function badge(): array
    {
        return match ($this->state()) {
            'paid' => ['Paid', 'bg-green-100 text-green-800'],
            'moved' => ['Moved to '.$this->carriedTo?->number, 'bg-gray-100 text-gray-600'],
            'partly' => ['₹'.number_format($this->due()).' due', 'bg-accent-50 text-accent-600'],
            'overdue' => ['Overdue', 'bg-red-100 text-red-700'],
            default => ['Due '.$this->due_date->format('d M'), 'bg-gray-100 text-gray-700'],
        };
    }
}
