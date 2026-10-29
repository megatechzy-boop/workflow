<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    public const CATEGORIES = ['rent' => 'Rent', 'tools' => 'Tools and software', 'ads' => 'Ads', 'office' => 'Office', 'travel' => 'Travel', 'other' => 'Other'];

    protected $fillable = ['date', 'title', 'category', 'amount', 'created_by'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
