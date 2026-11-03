<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** The Google login of whoever pressed "Connect Google" (one row); tokens are encrypted */
class GoogleAccount extends Model
{
    protected $fillable = ['access_token', 'refresh_token', 'expires_at', 'connected_by'];

    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return ['access_token' => 'encrypted', 'refresh_token' => 'encrypted', 'expires_at' => 'datetime'];
    }

    public static function current(): ?self
    {
        return self::query()->latest('id')->first();
    }
}
