<?php

namespace App\Models;

use App\Enums\Platform;
use App\Enums\PostType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Monthly package: price, platforms, posts per format and the services included */
class Package extends Model
{
    protected $fillable = ['name', 'price', 'platforms', 'static_posts', 'carousel_posts', 'reels', 'services', 'min_months', 'is_active'];

    protected $attributes = ['is_active' => true, 'platforms' => '[]', 'services' => '[]'];

    protected function casts(): array
    {
        return ['platforms' => 'array', 'services' => 'array', 'is_active' => 'boolean'];
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    /** Posts per month for one format */
    public function quota(PostType $type): int
    {
        return match ($type) {
            PostType::Post => $this->static_posts,
            PostType::Carousel => $this->carousel_posts,
            PostType::Reel => $this->reels,
        };
    }

    /** "Instagram, Facebook, LinkedIn" */
    public function platformLabel(): string
    {
        return collect($this->platforms)->map(fn ($p) => Platform::tryFrom($p)?->label())->filter()->implode(', ');
    }

    public function priceLabel(): string
    {
        return '₹'.number_format($this->price);
    }
}
