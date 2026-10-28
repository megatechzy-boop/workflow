<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Enums\PostType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Content extends Model
{
    protected $fillable = ['post_type', 'approver_id', 'status', 'current_version'];

    protected function casts(): array
    {
        return ['status' => ContentStatus::class];
    }

    /** "linkedin_carousel" => PostType::Carousel (upload rules isi se) */
    public function type(): PostType
    {
        return self::typeOf($this->post_type);
    }

    public static function typeOf(string $postType): PostType
    {
        return PostType::from(substr(strrchr($postType, '_'), 1));
    }

    public function typeLabel(): string
    {
        return CalendarItem::typeOptions()[$this->post_type] ?? $this->post_type;
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ContentVersion::class)->orderBy('number');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ContentComment::class)->orderBy('id');
    }
}
