<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A published Facebook post promoted as an ad (campaign + ad set + ad on the client's ad account) */
class MetaBoost extends Model
{
    protected $fillable = ['meta_post_id', 'meta_ad_account_id', 'daily_budget', 'days', 'city', 'radius_km', 'age_min', 'age_max',
        'status', 'campaign_id', 'adset_id', 'ad_id', 'error', 'created_by'];

    public function metaPost(): BelongsTo
    {
        return $this->belongsTo(MetaPost::class);
    }

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(MetaAdAccount::class, 'meta_ad_account_id');
    }

    public function audienceLabel(): string
    {
        return ($this->city ? $this->city.' +'.$this->radius_km.' km' : 'India').' · age '.$this->age_min.'–'.$this->age_max;
    }
}
