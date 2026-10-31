<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An ad account the connected Facebook login can use (the client pays for ads from it) */
class MetaAdAccount extends Model
{
    protected $fillable = ['account_id', 'name', 'currency'];

    public function label(): string
    {
        return $this->name.' ('.$this->account_id.')';
    }
}
