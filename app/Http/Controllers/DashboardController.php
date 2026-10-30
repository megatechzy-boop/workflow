<?php

namespace App\Http\Controllers;

use App\Enums\CalendarStatus;
use App\Enums\ContentStatus;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Attendance;
use App\Models\Calendar;
use App\Models\CalendarItem;
use App\Models\Content;
use App\Models\Invoice;
use App\Models\PointEntry;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Home page: a few numbers per role, each linking to where the work is */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $cards = [];
        $awaiting = [ContentStatus::Submitted, ContentStatus::Resubmitted];

        if ($user->role === Role::Client) {
            $cards[] = ['Calendar rows to check', CalendarItem::whereNull('client_status')
                ->whereHas('calendar', fn ($q) => $q->where('client_id', $user->client_id)->where('status', CalendarStatus::ApproverApproved))->count(),
                route('calendars.index', ['status' => CalendarStatus::ApproverApproved->value])];
            $cards[] = ['Content waiting for you', Content::where('status', ContentStatus::ClientReview)
                ->whereHas('task.item.calendar', fn ($q) => $q->where('client_id', $user->client_id))->count(),
                route('contents.index')];
        }

        if ($user->role->isCreator()) {
            $mine = Task::where('assigned_to', $user->id)->where('status', 'pending');
            $cards[] = ['My pending tasks', (clone $mine)->count(), route('tasks.index')];
            $cards[] = ['Due today', (clone $mine)->whereDate('deadline', today())->count(), route('tasks.index')];
            $cards[] = ['Late', (clone $mine)->whereDate('deadline', '<', today())->count(), route('tasks.index'), 'red'];
            $cards[] = ['My points this month', (int) PointEntry::where('user_id', $user->id)
                ->where('created_at', '>=', now()->startOfMonth())->sum('points'), route('leaderboard')];
        }

        if ($user->hasPermission(Permission::ApproveContent)) {
            $cards[] = ['Content to review', Content::whereIn('status', $awaiting)
                ->when(! $user->hasPermission(Permission::ViewAllTasks), fn ($q) => $q->where('approver_id', $user->id))->count(),
                route('contents.index')];
        }

        if ($user->hasPermission(Permission::ApproveCalendar)) {
            $cards[] = ['Calendars to approve', Calendar::where('status', CalendarStatus::Submitted)->count(),
                route('calendars.index', ['status' => CalendarStatus::Submitted->value])];
        }

        if ($user->hasPermission(Permission::ViewAllTasks)) {
            $cards[] = ['Late tasks (team)', Task::where('status', 'pending')->whereDate('deadline', '<', today())->count(), route('tasks.index'), 'red'];
            $cards[] = ['Waiting for customers', Content::where('status', ContentStatus::ClientReview)->count()
                + Calendar::where('status', CalendarStatus::ApproverApproved)->count(), route('contents.index', ['status' => ContentStatus::ClientReview->value])];
            $cards[] = ['Posts this week', CalendarItem::whereBetween('post_date', [now()->startOfWeek(), now()->endOfWeek()])->count(), route('schedule')];
            $cards[] = ['Ready to publish', Content::whereIn('status', [ContentStatus::Approved, ContentStatus::Scheduled])->count(),
                route('contents.index', ['status' => ContentStatus::Approved->value])];
        }

        // Team: today's check-in and this month's attendance
        $attendance = $user->role === Role::Client ? null : [
            'today' => Attendance::where('user_id', $user->id)->whereDate('date', today())->first(),
            'month' => Attendance::where('user_id', $user->id)->whereBetween('date', [now()->startOfMonth(), now()->endOfMonth()])->pluck('status')->countBy(),
        ];

        $planner = $user->hasPermission(Permission::ViewAllTasks) || $user->hasPermission(Permission::ApproveContent);

        return view('dashboard', [
            'cards' => $cards, 'myPackage' => $this->customerPackage($user), 'attendance' => $attendance,
            // Customer: their bills, newest first
            'myBills' => $user->role === Role::Client && $user->client_id
                ? Invoice::with('payments')->where('client_id', $user->client_id)->orderByDesc('invoice_date')->orderByDesc('id')->limit(12)->get()
                : collect(),
            'todo' => $user->role === Role::Client ? [] : $this->todaysWork($user),
            'week' => $planner ? $this->week($user) : null,
            'brands' => $planner ? $this->brandProgress($user) : [],
        ]);
    }

    /** "Today's work": [label, detail, badge, badge colour, url] — own tasks, content to review, team's late tasks */
    private function todaysWork($user): array
    {
        $rows = [];
        if ($user->role->isCreator()) {
            foreach (Task::with('item.calendar.client')->where('assigned_to', $user->id)->dueSoon()->limit(8)->get() as $t) {
                $formats = collect($t->item->formats())->map(fn ($f) => $f->label())->implode(' + ');
                $rows[] = [$t->item->calendar->client->name, $formats.' · post '.$t->item->post_date->format('d M'), $t->dueLabel(),
                    $t->deadline->isPast() && ! $t->deadline->isToday() ? 'bg-red-100 text-red-800' : 'bg-accent-100 text-accent-600', route('tasks.show', $t)];
            }
        }
        if ($user->hasPermission(Permission::ApproveContent)) {
            $review = Content::with('task.item.calendar.client')->whereIn('status', [ContentStatus::Submitted, ContentStatus::Resubmitted])
                ->when(! $user->hasPermission(Permission::ViewAllTasks), fn ($q) => $q->where('approver_id', $user->id))
                ->oldest('updated_at')->limit(8)->get();
            foreach ($review as $c) {
                $rows[] = [$c->task->item->calendar->client->name, $c->typeLabel().' · V'.$c->current_version, 'Review', 'bg-purple-100 text-purple-800', route('contents.show', $c)];
            }
        }
        if ($user->hasPermission(Permission::ViewAllTasks)) {
            $late = Task::with('item.calendar.client', 'assignee')->where('status', 'pending')->whereDate('deadline', '<', today())->orderBy('deadline')->limit(5)->get();
            foreach ($late as $t) {
                $rows[] = [$t->item->calendar->client->name, $t->assignee->name.' · post '.$t->item->post_date->format('d M'), $t->dueLabel(), 'bg-red-100 text-red-800', route('tasks.show', $t)];
            }
        }

        return array_slice($rows, 0, 10);
    }

    /** This week (Mon–Sun): posts per day with their live status; other people's drafts stay hidden */
    private function week($user): array
    {
        $start = now()->startOfWeek();
        $items = CalendarItem::with(['calendar.client', 'task.contents'])
            ->whereBetween('post_date', [$start, $start->copy()->endOfWeek()])
            ->whereHas('calendar', \App\Models\User::draftFilter($user))
            ->orderBy('post_date')->get()->groupBy(fn ($i) => $i->post_date->toDateString());

        return collect(range(0, 6))->map(fn ($d) => ['day' => $start->copy()->addDays($d), 'items' => $items[$start->copy()->addDays($d)->toDateString()] ?? collect()])->all();
    }

    /** This month per brand: posts planned / live, and the package quota */
    private function brandProgress($user): array
    {
        $items = CalendarItem::with(['calendar.client.package', 'task.contents'])
            ->whereBetween('post_date', [now()->startOfMonth(), now()->endOfMonth()])
            ->whereHas('calendar', fn ($q) => $q->where('status', '!=', CalendarStatus::Draft))
            ->get();

        return $items->groupBy('calendar.client_id')->map(function ($rows) {
            $client = $rows->first()->calendar->client;
            $statuses = $rows->map(fn ($i) => $i->task?->contents->first()?->status);
            $quota = $client->package ? collect(\App\Enums\PostType::cases())->sum(fn ($t) => $client->package->quota($t)) : null;

            return [
                'name' => $client->name,
                'planned' => $rows->count(),
                'live' => $statuses->filter(fn ($s) => $s === ContentStatus::Published)->count(),
                'ready' => $statuses->filter(fn ($s) => in_array($s, [ContentStatus::Approved, ContentStatus::Scheduled], true))->count(),
                'quota' => $quota ?: null,
                'url' => route('calendars.show', $rows->first()->calendar),
            ];
        })->sortByDesc('planned')->values()->all();
    }

    /** Customer: their package and this month's published posts per format */
    private function customerPackage($user): ?array
    {
        $package = $user->role === Role::Client ? $user->client?->package : null;
        if (! $package) {
            return null;
        }
        $published = Content::where('status', ContentStatus::Published)
            ->whereHas('task.item', fn ($q) => $q->whereBetween('post_date', [now()->startOfMonth(), now()->endOfMonth()])
                ->whereHas('calendar', fn ($q) => $q->where('client_id', $user->client_id)))
            ->pluck('post_type')
            ->countBy(fn ($type) => Content::typeOf($type)->value);

        return ['package' => $package, 'since' => $user->client->package_start, 'published' => $published];
    }
}
