<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Google review of a client's location (synced by gbp:reviews).
 * pending = a person replies · scheduled = auto reply at reply_due_at · draft = 3-star reply waiting for approval · replied
 */
class GbpReview extends Model
{
    public const STATUSES = ['pending' => 'To reply', 'draft' => 'Approval', 'scheduled' => 'Auto reply scheduled', 'replied' => 'Replied'];

    protected $fillable = ['client_id', 'gbp_location_id', 'review_id', 'review_name', 'reviewer_name', 'rating', 'comment', 'reviewed_at',
        'status', 'draft', 'template_id', 'templated_at', 'reply_due_at', 'attempts', 'error', 'reply', 'replied_at'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime', 'templated_at' => 'datetime', 'reply_due_at' => 'datetime', 'replied_at' => 'datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(GbpLocation::class, 'gbp_location_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ReviewReplyLog::class);
    }
}
