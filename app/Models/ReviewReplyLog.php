<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One reply sent to Google; approved_by null = sent automatically */
class ReviewReplyLog extends Model
{
    protected $fillable = ['gbp_review_id', 'template_id', 'final_text', 'sent_at', 'approved_by'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
