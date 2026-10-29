<?php

namespace App\Models;

use App\Support\DocNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bill for a client: monthly (month set, from its package) or one-off (month null).
 * items: [{name, detail, qty, rate}]; amount = total incl. GST; paid_at = when fully paid.
 */
class Invoice extends Model
{
    protected $fillable = ['number', 'client_id', 'invoice_date', 'month', 'package_name', 'items', 'gst_rate', 'amount', 'due_date', 'paid_at'];

    protected function casts(): array
    {
        return ['month' => 'date', 'invoice_date' => 'date', 'due_date' => 'date', 'paid_at' => 'datetime', 'items' => 'array', 'gst_rate' => 'float'];
    }

    protected static function booted(): void
    {
        static::creating(function (Invoice $i) {
            $i->number ??= DocNumber::next('invoices', config('business.invoice_start'));
            $i->invoice_date ??= today();
            $i->gst_rate ??= config('business.gst_rate');
            $i->amount = $i->subtotal() + $i->gstAmount();
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('date')->orderBy('id');
    }

    public function subtotal(): int
    {
        return (int) collect($this->items)->sum(fn ($i) => $i['qty'] * $i['rate']);
    }

    public function gstAmount(): int
    {
        return (int) round($this->subtotal() * ($this->gst_rate ?? 0) / 100);
    }

    public function received(): int
    {
        return (int) $this->payments->sum('amount');
    }

    public function due(): int
    {
        return max(0, $this->amount - $this->received());
    }

    public function syncPaid(): void
    {
        $this->load('payments');
        $this->update(['paid_at' => $this->due() === 0 ? ($this->paid_at ?? now()) : null]);
    }

    /** paid | partly | overdue | due */
    public function state(): string
    {
        return match (true) {
            (bool) $this->paid_at => 'paid',
            $this->due_date->lt(today()) => 'overdue',
            $this->received() > 0 => 'partly',
            default => 'due',
        };
    }
}
