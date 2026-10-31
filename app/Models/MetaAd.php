<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An ad built in the app: campaign + ad set + ad on the client's ad account */
class MetaAd extends Model
{
    public const GOALS = [
        'whatsapp' => 'WhatsApp messages',
        'messages' => 'Messenger messages',
        'calls' => 'Phone calls',
        'website' => 'Website visits',
        'awareness' => 'Reach (awareness)',
    ];

    protected $fillable = ['client_id', 'meta_ad_account_id', 'name', 'goal', 'daily_budget', 'start_on', 'end_on', 'city', 'radius_km',
        'age_min', 'age_max', 'gender', 'interests', 'primary_text', 'headline', 'url', 'phone', 'image_path', 'status',
        'campaign_id', 'adset_id', 'ad_id', 'error', 'created_by'];

    protected function casts(): array
    {
        return ['start_on' => 'date', 'end_on' => 'date'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(MetaAdAccount::class, 'meta_ad_account_id');
    }

    public function audienceLabel(): string
    {
        return ($this->city ? $this->city.' +'.$this->radius_km.' km' : 'India').' · '.$this->age_min.'–'.$this->age_max
            .($this->gender !== 'all' ? ' · '.$this->gender : '').($this->interests ? ' · '.$this->interests : '');
    }

    public function adsManagerUrl(): ?string
    {
        return $this->campaign_id ? 'https://adsmanager.facebook.com/adsmanager/manage/campaigns?act='
            .substr($this->adAccount->account_id, 4).'&selected_campaign_ids='.$this->campaign_id : null;
    }
}
