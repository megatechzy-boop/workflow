<?php

namespace App\Models;

use App\Support\ReviewTemplates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Client extends Model
{
    protected $fillable = ['name', 'contact_email', 'phone', 'address', 'notes', 'is_active', 'package_id', 'package_start', 'onboarding_token', 'onboarding', 'onboarded_at', 'meta_page_id', 'meta_ad_account_id', 'is_internal', 'meta_pixel_id',
        'gbp_auto_reply', 'gbp_language', 'gbp_auto_language', 'is_healthcare', 'caption_language', 'caption_style'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_internal' => 'boolean', 'gbp_auto_reply' => 'boolean', 'gbp_auto_language' => 'boolean', 'is_healthcare' => 'boolean', 'package_start' => 'date', 'onboarding' => 'array', 'onboarded_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        // Every new client starts with its own copy of the default review reply templates
        static::created(fn (Client $client) => ReviewTemplates::copyDefaultsTo($client));
    }

    /** Ad account boosts are charged to (the client's own) */
    public function metaAdAccount(): BelongsTo
    {
        return $this->belongsTo(MetaAdAccount::class);
    }

    /** Facebook Page (and its Instagram) where approved posts are published */
    public function metaPage(): BelongsTo
    {
        return $this->belongsTo(MetaPage::class);
    }

    /** Google Business Profile location (reviews and GBP posts) */
    public function gbpLocation(): HasOne
    {
        return $this->hasOne(GbpLocation::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Team members who work on this brand (Admin > Users > Brands) */
    public function team(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /** Clients ready for ads (a Page and an ad account), within the user's brands */
    public static function readyForAds(User $user)
    {
        return self::where('is_active', true)->whereNotNull('meta_page_id')->whereNotNull('meta_ad_account_id')
            ->when($user->brandLimit(), fn ($q, $ids) => $q->whereIn('id', $ids))->orderBy('name')->get();
    }
}
