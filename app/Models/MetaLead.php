<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Someone who filled a client's Facebook / Instagram lead form (the client's customer, not ours) */
class MetaLead extends Model
{
    public const STATUSES = ['new' => 'New', 'contacted' => 'Contacted', 'won' => 'Won', 'lost' => 'Lost'];

    protected $fillable = ['meta_page_id', 'client_id', 'lead_id', 'form_name', 'name', 'phone', 'email', 'fields', 'lead_at', 'status'];

    protected function casts(): array
    {
        return ['fields' => 'array', 'lead_at' => 'datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(MetaPage::class, 'meta_page_id');
    }

    /** "919876543210" for tel: / wa.me */
    public function phoneDigits(): string
    {
        $digits = ltrim(preg_replace('/\D/', '', (string) $this->phone), '0');

        return strlen($digits) === 10 ? '91'.$digits : $digits;
    }
}
