<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Someone who filled a client's Facebook / Instagram lead form (the client's customer, not ours) */
class MetaLead extends Model
{
    public const STATUSES = ['new' => 'New', 'contacted' => 'Contacted', 'won' => 'Won', 'lost' => 'Lost'];

    protected $fillable = ['meta_page_id', 'client_id', 'lead_id', 'form_name', 'name', 'phone', 'email', 'fields', 'lead_at', 'status', 'phone_key', 'is_repeat', 'follow_up_on'];

    protected function casts(): array
    {
        return ['fields' => 'array', 'lead_at' => 'datetime', 'is_repeat' => 'boolean', 'follow_up_on' => 'date'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(MetaPage::class, 'meta_page_id');
    }

    /** Newest first */
    public function remarks(): HasMany
    {
        return $this->hasMany(MetaLeadRemark::class)->latest()->latest('id');
    }

    /** Last 10 digits of a phone number, so "+91 98765 43210" and "09876543210" match (null when too short) */
    public static function phoneKey(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        return strlen($digits) >= 10 ? substr($digits, -10) : null;
    }

    /** Follow-up is today or was missed, and the lead is still open */
    public function followUpDue(): bool
    {
        return $this->follow_up_on && $this->follow_up_on->lte(today()) && ! in_array($this->status, ['won', 'lost'], true);
    }

    /** "919876543210" for tel: / wa.me */
    public function phoneDigits(): string
    {
        $digits = ltrim(preg_replace('/\D/', '', (string) $this->phone), '0');

        return strlen($digits) === 10 ? '91'.$digits : $digits;
    }
}
