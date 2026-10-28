<?php

namespace App\Models;

use App\Enums\CalendarStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Calendar extends Model
{
    protected $fillable = ['client_id', 'month', 'status', 'created_by'];

    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return ['month' => 'date', 'status' => CalendarStatus::class];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CalendarItem::class)->orderBy('post_date')->orderBy('id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(CalendarReview::class)->latest('id');
    }
}
