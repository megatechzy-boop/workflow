<?php

namespace App\Models;

use App\Support\Notify;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    /**
     * Customer approve karte hi: jin posts ka task nahi bana, unhe sahi role (Reel => Editor, baaki => Designer)
     * ke us member ko do jiske paas sabse kam pending tasks hain. SMM baad mein badal sakta hai.
     */
    public static function autoAssign(Calendar $calendar, int $assignedBy): array
    {
        $creators = User::creators();
        $load = static::where('status', 'pending')->selectRaw('assigned_to, COUNT(*) as n')
            ->groupBy('assigned_to')->pluck('n', 'assigned_to')->all();

        $created = [];
        foreach ($calendar->items()->doesntHave('task')->get() as $item) {
            $member = $creators->filter(fn ($u) => in_array($u->role, $item->assigneeRoles(), true))
                ->sortBy(fn ($u) => [$load[$u->id] ?? 0, $u->name])->first();
            if (! $member) {
                continue; // us role ka koi active member nahi: SMM haath se assign karega
            }

            $load[$member->id] = ($load[$member->id] ?? 0) + 1;
            $created[] = $item->task()->create([
                'assigned_to' => $member->id,
                'assigned_by' => $assignedBy,
                'deadline' => static::deadlineFor($item->post_date),
            ]);
        }

        return $created;
    }

    public function notifyAssignee(): void
    {
        Notify::send([$this->assignee], 'Naya task mila',
            $this->item->calendar->client->name.' · post date '.$this->item->post_date->format('d M').' · deadline '.$this->deadline->format('d M'),
            route('tasks.show', $this));
    }

    public function isOverdue(): bool
    {
        return $this->status === 'pending' && $this->deadline->lt(today());
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(CalendarItem::class, 'calendar_item_id');
    }

    public function contents(): HasMany
    {
        return $this->hasMany(Content::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
