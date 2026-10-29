<?php

namespace App\Models;

use App\Enums\CalendarStatus;
use App\Enums\PostType;
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

    /** Rows per format, for the package counter: ['post' => 4, 'carousel' => 2, 'reel' => 5] */
    public function formatCounts(): array
    {
        $counts = array_fill_keys(array_map(fn (PostType $t) => $t->value, PostType::cases()), 0);
        foreach ($this->items as $item) {
            foreach ($item->formats() as $format) {
                $counts[$format->value]++;
            }
        }

        return $counts;
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(CalendarReview::class)->latest('id');
    }
}
