<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A note on a Meta lead ("Called, will visit tomorrow"), with an optional next follow-up date */
class MetaLeadRemark extends Model
{
    public const QUICK = ['No answer', 'Call back', 'Interested', 'Price sent', 'Not interested'];

    protected $fillable = ['meta_lead_id', 'user_id', 'remark', 'follow_up_on'];

    protected function casts(): array
    {
        return ['follow_up_on' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
