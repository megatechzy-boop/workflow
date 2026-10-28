<?php

namespace App\Models;

use App\Enums\Platform;
use App\Enums\PostType;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CalendarItem extends Model
{
    protected $fillable = ['post_date', 'post_types', 'creative_copy', 'visual_brief', 'brief', 'reference_link', 'remarks', 'client_status', 'client_comment'];

    protected function casts(): array
    {
        return ['post_date' => 'date', 'post_types' => 'array'];
    }

    /** All options: ['linkedin_post' => 'LinkedIn Post', ...] (there is no YouTube Carousel) */
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

    /**
     * Formats to upload: one design per format serves every platform.
     * ["instagram_post", "linkedin_post", "instagram_reel"] => [PostType::Post, PostType::Reel]
     */
    public function formats(): array
    {
        $types = collect($this->post_types ?? [])->map(fn ($key) => Content::typeOf($key));

        return array_values(array_filter(PostType::cases(), fn ($t) => $types->contains($t)));
    }

    /**
     * Formats a role works on: Graphic Designer => Post/Carousel, Video Editor => Reel, others => all.
     * Formats not relevant to the role are skipped.
     */
    public function formatsFor(Role $role): array
    {
        return array_values(array_filter($this->formats(), fn (PostType $f) => match ($role) {
            Role::GraphicDesigner => $f !== PostType::Reel,
            Role::VideoEditor => $f === PostType::Reel,
            default => true,
        }));
    }

    /** "Post · Instagram, Facebook, LinkedIn" */
    public function formatLabel(PostType $format): string
    {
        $platforms = collect($this->post_types ?? [])
            ->filter(fn ($key) => Content::typeOf($key) === $format)
            ->map(fn ($key) => Platform::from(strstr($key, '_', true))->label())
            ->implode(', ');

        return $format->label().($platforms ? ' · '.$platforms : '');
    }

    /**
     * Who can take this post: Reel => Video Editor, Post/Carousel => Graphic Designer.
     * shortcut: one task per item, so a "Post + Reel" mix shows both roles; split tasks per format if different people are needed.
     */
    public function assigneeRoles(): array
    {
        $types = collect($this->post_types ?? [])->map(fn ($key) => Content::typeOf($key));

        return array_values(array_filter([
            $types->contains(fn ($t) => $t !== PostType::Reel) ? Role::GraphicDesigner : null,
            $types->contains(PostType::Reel) ? Role::VideoEditor : null,
        ]));
    }

    /** Photos/videos the Customer or team attached to this post */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class)->latest();
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
