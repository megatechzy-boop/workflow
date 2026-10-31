<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One content sent to one platform; meta:publish moves it queued → (processing) → done | failed */
class MetaPost extends Model
{
    protected $fillable = ['content_id', 'meta_page_id', 'platform', 'status', 'container_id', 'post_id', 'error', 'attempts', 'queued_by', 'publish_at'];

    protected function casts(): array
    {
        return ['publish_at' => 'datetime'];
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(MetaPage::class, 'meta_page_id');
    }
}
