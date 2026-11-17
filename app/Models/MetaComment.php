<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A Facebook / Instagram comment on a post we published; answered from the Comments inbox */
class MetaComment extends Model
{
    protected $fillable = ['meta_post_id', 'client_id', 'platform', 'comment_id', 'author', 'message', 'commented_at', 'reply', 'replied_at', 'replied_by', 'ignored_at', 'ignored_by'];

    protected function casts(): array
    {
        return ['commented_at' => 'datetime', 'replied_at' => 'datetime', 'ignored_at' => 'datetime'];
    }

    public function metaPost(): BelongsTo
    {
        return $this->belongsTo(MetaPost::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function ignorer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ignored_by');
    }

    public function replier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_by');
    }
}
