<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A Google Business Profile post (update, offer or event) for one client's location */
class GbpPost extends Model
{
    public const TYPES = ['update' => 'Update', 'offer' => 'Offer', 'event' => 'Event'];

    public const STATUSES = ['scheduled' => 'Scheduled', 'publishing' => 'Publishing', 'published' => 'Published', 'failed' => 'Failed'];

    public const CTAS = ['CALL' => 'Call now', 'BOOK' => 'Book', 'ORDER' => 'Order online', 'SHOP' => 'Buy', 'LEARN_MORE' => 'Learn more', 'SIGN_UP' => 'Sign up'];

    protected $fillable = ['client_id', 'location_id', 'status', 'post_type', 'caption', 'image_path', 'cta_type', 'cta_url', 'title', 'starts_at', 'ends_at',
        'coupon', 'terms', 'scheduled_at', 'retry_at', 'attempts', 'published_at', 'google_post_name', 'search_url', 'error', 'created_by'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'scheduled_at' => 'datetime', 'retry_at' => 'datetime', 'published_at' => 'datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(GbpLocation::class, 'location_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Scheduled and failed posts can still be changed */
    public function editable(): bool
    {
        return in_array($this->status, ['scheduled', 'failed'], true);
    }
}
