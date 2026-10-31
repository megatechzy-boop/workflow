<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** The Facebook login of whoever pressed "Connect Facebook" (one row); its token runs the ads */
class MetaConnection extends Model
{
    protected $fillable = ['user_token'];

    protected $hidden = ['user_token'];

    protected function casts(): array
    {
        return ['user_token' => 'encrypted'];
    }

    public static function token(): ?string
    {
        return self::query()->latest('id')->first()?->user_token;
    }
}
