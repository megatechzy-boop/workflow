<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A Google Business Profile location the Admin connected; linked to at most one client */
class GbpLocation extends Model
{
    protected $fillable = ['client_id', 'account_name', 'location_name', 'title', 'city', 'phone', 'reviews_synced_at', 'reviews_error'];

    protected function casts(): array
    {
        return ['reviews_synced_at' => 'datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** Path the v4 API wants for reviews and posts: accounts/123/locations/456 */
    public function v4Path(): string
    {
        return $this->account_name.'/'.$this->location_name;
    }

    public function label(): string
    {
        return $this->title.($this->city ? ' · '.$this->city : '');
    }
}
