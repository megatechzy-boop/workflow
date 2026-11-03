<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Reply template for Google reviews; client_id null = the default set copied to new clients */
class ReviewTemplate extends Model
{
    protected $fillable = ['client_id', 'rating_group', 'language', 'body', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
