<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentVersion extends Model
{
    protected $fillable = ['number', 'caption', 'hashtags', 'submitted_by'];

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function media(): HasMany
    {
        return $this->hasMany(ContentMedia::class)->orderBy('sort_order');
    }
}
