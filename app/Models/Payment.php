<?php

namespace App\Models;

use App\Support\DocNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Money received against an invoice; each one is a payment receipt */
class Payment extends Model
{
    public const METHODS = ['Account Transfer', 'UPI', 'Cash', 'Cheque'];

    protected $fillable = ['invoice_id', 'number', 'date', 'method', 'deposited_to', 'amount'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    protected static function booted(): void
    {
        static::creating(fn (Payment $p) => $p->number ??= DocNumber::next('payments', config('business.receipt_start')));
        // Keep the invoice's paid date in step with what was received
        static::saved(fn (Payment $p) => $p->invoice->syncPaid());
        static::deleted(fn (Payment $p) => $p->invoice->syncPaid());
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
