<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Salary marked as paid for one member and month (amount fixed at the time of payment) */
class SalaryPayment extends Model
{
    protected $fillable = ['user_id', 'month', 'amount', 'paid_at'];

    protected function casts(): array
    {
        return ['month' => 'date', 'paid_at' => 'datetime'];
    }
}
