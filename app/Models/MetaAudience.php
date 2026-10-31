<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A custom / lookalike audience on the client's ad account, made from the app and picked in New ad */
class MetaAudience extends Model
{
    public const TYPES = [
        'page' => 'Facebook Page engagers',
        'instagram' => 'Instagram engagers',
        'website' => 'Website visitors (pixel)',
        'list' => 'Customer list (phones / emails)',
        'lookalike' => 'Lookalike (people similar to an audience)',
    ];

    protected $fillable = ['client_id', 'meta_ad_account_id', 'name', 'type', 'days', 'lookalike_percent', 'source_id', 'list_count',
        'audience_id', 'status', 'error', 'created_by'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(MetaAdAccount::class, 'meta_ad_account_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_id');
    }

    public function detail(): string
    {
        return match ($this->type) {
            'list' => number_format((int) $this->list_count).' contacts',
            'lookalike' => $this->lookalike_percent.'% of India like "'.($this->source->name ?? '—').'"',
            default => 'last '.$this->days.' days',
        };
    }
}
