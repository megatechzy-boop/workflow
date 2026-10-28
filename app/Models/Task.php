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

    /** Auto deadline: 2 days before the posting date, never before today */
    public static function deadlineFor(Carbon $postDate): Carbon
    {
        return $postDate->copy()->subDays(2)->max(today());
    }

    /**
     * On Customer approval: give every post without a task to the member of the right role
     * (Reel => Editor, others => Designer) with the fewest pending tasks. The SMM can change it later.
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
                continue; // no active member with that role: the SMM assigns it manually
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
        Notify::send([$this->assignee], 'New task assigned',
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
