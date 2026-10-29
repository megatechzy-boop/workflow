<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Monthly bill for a client, from its package price */
class Invoice extends Model
{
    protected $fillable = ['client_id', 'month', 'package_name', 'amount', 'due_date', 'paid_at'];

    protected function casts(): array
    {
        return ['month' => 'date', 'due_date' => 'date', 'paid_at' => 'datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** paid | overdue | due */
    public function state(): string
    {
        return $this->paid_at ? 'paid' : ($this->due_date->lt(today()) ? 'overdue' : 'due');
    }
}
