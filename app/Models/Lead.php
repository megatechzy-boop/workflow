<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Someone who filled the public enquiry form */
class Lead extends Model
{
    public const NEEDS = ['Social Media Marketing', 'SEO', 'Google / Meta Ads', 'Website Development', 'Software / App Development',
        'Branding & Creative', 'AI & Automation', 'Not sure yet'];

    public const BUDGETS = ['Under ₹5,000', '₹5,000 - ₹10,000 (Starter/Growth)', '₹10,000 - ₹20,000 (Growth/Scale)',
        '₹20,000 - ₹50,000 (Scale/Premium)', '₹50,000+', 'Not sure - please suggest'];

    public const TIMELINES = ['Immediately', 'Within 2 weeks', 'Within a month', 'Just exploring'];

    public const STATUSES = ['new' => 'New', 'contacted' => 'Contacted', 'won' => 'Won', 'lost' => 'Lost'];

    protected $fillable = ['name', 'business', 'phone', 'email', 'needs', 'goal', 'budget', 'timeline', 'source', 'notes', 'status', 'remarks', 'client_id'];

    protected $attributes = ['status' => 'new'];

    protected function casts(): array
    {
        return ['needs' => 'array'];
    }

    /** "098765 43210" / "+91 98765-43210" => "919876543210" (for tel: and wa.me) */
    public function phoneDigits(): string
    {
        $digits = ltrim(preg_replace('/\D/', '', $this->phone), '0');

        return strlen($digits) === 10 ? '91'.$digits : $digits;
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
