<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentComment extends Model
{
    protected $fillable = ['content_version_id', 'user_id', 'action', 'body', 'slide', 'at_seconds'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ContentVersion::class, 'content_version_id');
    }

    /** 75 => "1:15" */
    public function timestamp(): ?string
    {
        return $this->at_seconds === null ? null : intdiv($this->at_seconds, 60).':'.str_pad($this->at_seconds % 60, 2, '0', STR_PAD_LEFT);
    }
}
