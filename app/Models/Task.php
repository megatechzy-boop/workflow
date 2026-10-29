<?php

namespace App\Models;

use App\Support\Notify;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Task extends Model
{
    protected $fillable = ['assigned_to', 'assigned_by', 'deadline', 'status', 'submitted_at'];

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return ['deadline' => 'date', 'submitted_at' => 'datetime', 'reminded_on' => 'date'];
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

        // Brand team (Admin > Users > Brands): members ticked for this brand, plus members with no brands (they do all brands)
        $links = DB::table('client_user')->get(['client_id', 'user_id']);
        $hasBrands = $links->pluck('user_id')->unique();
        $brandTeam = $links->where('client_id', $calendar->client_id)->pluck('user_id');

        $created = [];
        foreach ($calendar->items()->doesntHave('task')->get() as $item) {
            $roleMembers = $creators->filter(fn ($u) => in_array($u->role, $item->assigneeRoles(), true));
            $pool = $roleMembers->filter(fn ($u) => $brandTeam->contains($u->id) || ! $hasBrands->contains($u->id));
            $member = ($pool->isNotEmpty() ? $pool : $roleMembers) // nobody fits: any member of the role rather than no one
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

    /**
     * Daily 9 am digest: ONE message per member listing their pending tasks that are
     * overdue, due today or due tomorrow. At most once a day (reminded_on). Returns how many members got one.
     */
    public static function sendDeadlineReminders(): int
    {
        $tasks = static::with(['assignee', 'item.calendar.client'])
            ->where('status', 'pending')
            ->whereDate('deadline', '<=', today()->addDay())
            ->where(fn ($q) => $q->whereNull('reminded_on')->orWhereDate('reminded_on', '<', today()))
            ->orderBy('deadline')
            ->get();

        $byMember = $tasks->groupBy('assigned_to');
        foreach ($byMember as $memberTasks) {
            $lines = $memberTasks->map(fn (Task $task) => '• '.$task->item->calendar->client->name
                .' · post '.$task->item->post_date->format('d M').' · '.match (true) {
                    $task->deadline->isTomorrow() => 'due tomorrow',
                    $task->deadline->isToday() => 'due today',
                    default => 'overdue since '.$task->deadline->format('d M'),
                });
            Notify::send([$memberTasks->first()->assignee], 'Your tasks for '.today()->format('d M'),
                $memberTasks->count().' task(s) need you:'."\n".$lines->implode("\n"), route('tasks.index'));
            static::whereKey($memberTasks->pluck('id'))->update(['reminded_on' => today()]);
        }

        return $byMember->count();
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
