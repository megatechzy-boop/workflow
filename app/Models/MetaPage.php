<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A Facebook Page the Admin connected (page token never leaves the server), with its Instagram account if linked */
class MetaPage extends Model
{
    protected $fillable = ['page_id', 'name', 'access_token', 'ig_user_id', 'ig_username'];

    protected $hidden = ['access_token'];

    protected function casts(): array
    {
        return ['access_token' => 'encrypted'];
    }

    public function label(): string
    {
        return $this->name.($this->ig_username ? ' · @'.$this->ig_username : '');
    }
}
