<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A Facebook / Instagram comment on a post we published; answered from the Comments inbox */
class MetaComment extends Model
{
    protected $fillable = ['meta_post_id', 'client_id', 'platform', 'comment_id', 'author', 'message', 'commented_at', 'reply', 'replied_at', 'replied_by'];

    protected function casts(): array
    {
        return ['commented_at' => 'datetime', 'replied_at' => 'datetime'];
    }

    public function metaPost(): BelongsTo
    {
        return $this->belongsTo(MetaPost::class);
    }

    public function replier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_by');
    }
}
