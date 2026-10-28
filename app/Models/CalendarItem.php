<?php

namespace App\Models;

use App\Enums\Platform;
use App\Enums\PostType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CalendarItem extends Model
{
    protected $fillable = ['post_date', 'post_types', 'creative_copy', 'visual_brief', 'brief', 'reference_link', 'remarks'];

    protected function casts(): array
    {
        return ['post_date' => 'date', 'post_types' => 'array'];
    }

    /** Sab options: ['linkedin_post' => 'LinkedIn Post', ...] (YouTube Carousel nahi hota) */
    public static function typeOptions(): array
    {
        $options = [];
        foreach (Platform::cases() as $platform) {
            foreach (PostType::cases() as $type) {
                if ($platform === Platform::YouTube && $type === PostType::Carousel) {
                    continue;
                }
                $options[$platform->value.'_'.$type->value] = $platform->label().' '.$type->label();
            }
        }

        return $options;
    }

    /** Sheet jaisa: "LinkedIn Post, Instagram Reel" */
    public function typeLabel(): string
    {
        $options = self::typeOptions();

        return collect($this->post_types ?? [])->map(fn ($key) => $options[$key] ?? $key)->implode(', ');
    }

    public function task(): HasOne
    {
        return $this->hasOne(Task::class);
    }

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class);
    }
}
