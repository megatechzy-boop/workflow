<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Task extends Model
{
    protected $fillable = ['assigned_to', 'assigned_by', 'deadline', 'status', 'submitted_at'];

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return ['deadline' => 'date', 'submitted_at' => 'datetime'];
    }

    /** Auto deadline: posting date se 2 din pehle, par aaj se pehle kabhi nahi */
    public static function deadlineFor(Carbon $postDate): Carbon
    {
        return $postDate->copy()->subDays(2)->max(today());
    }

    public function isOverdue(): bool
    {
        return $this->status === 'pending' && $this->deadline->lt(today());
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(CalendarItem::class, 'calendar_item_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
