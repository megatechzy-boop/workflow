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

        return view('dashboard', ['cards' => $cards, 'myPackage' => $this->customerPackage($user), 'attendance' => $attendance]);
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
