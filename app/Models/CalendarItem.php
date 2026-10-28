<?php

namespace App\Models;

use App\Enums\PostType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarItem extends Model
{
    protected $fillable = ['post_date', 'post_type', 'creative_copy', 'visual_brief', 'brief', 'reference_link', 'remarks'];

    protected function casts(): array
    {
        return ['post_date' => 'date', 'post_type' => PostType::class];
    }

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class);
    }
}
