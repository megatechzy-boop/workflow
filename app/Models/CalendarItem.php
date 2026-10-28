<?php

namespace App\Models;

use App\Enums\Platform;
use App\Enums\PostType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarItem extends Model
{
    protected $fillable = ['post_date', 'platform', 'post_type', 'creative_copy', 'visual_brief', 'brief', 'reference_link', 'remarks'];

    protected function casts(): array
    {
        return ['post_date' => 'date', 'platform' => Platform::class, 'post_type' => PostType::class];
    }

    /** Sheet jaisa: "LinkedIn Post" */
    public function typeLabel(): string
    {
        return trim(($this->platform?->label() ?? '').' '.$this->post_type->label());
    }

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class);
    }
}
