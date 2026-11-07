<?php

namespace App\Http\Controllers;

use App\Enums\CalendarStatus;
use App\Enums\Role;
use App\Models\CalendarItem;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Month grid: every post of the month on its day, with its live status */
class ScheduleController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $isCustomer = $user->role === Role::Client;
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->month)
            ? Carbon::createFromFormat('Y-m-d', $request->month.'-01')->startOfDay()
            : now()->startOfMonth();
        $clientId = $isCustomer ? $user->client_id : ($request->client ? (int) $request->client : null);

        $range = [$month, $month->copy()->endOfMonth()];
        $items = CalendarItem::with(['calendar.client', 'task.contents.metaPosts', 'task.assignee'])
            // the calendar date, or a Facebook / Instagram time set on another day
            ->where(fn ($q) => $q->whereBetween('post_date', $range)
                ->orWhereHas('task.contents.metaPosts', fn ($m) => $m->whereBetween('publish_at', $range)))
            // Designers/Editors: only their own posts
            ->when($user->seesOnlyOwnRows(), fn ($q) => $q->whereHas('task', fn ($t) => $t->where('assigned_to', $user->id)))
            ->whereHas('calendar', fn ($q) => $q
                ->tap(\App\Models\User::draftFilter($user))
                ->when($clientId, fn ($c) => $c->where('client_id', $clientId))
                ->when($user->brandLimit(), fn ($c, $ids) => $c->whereIn('client_id', $ids))
                // Customers only see calendars that were sent to them
                ->when($isCustomer, fn ($c) => $c->whereIn('status', [CalendarStatus::ApproverApproved, CalendarStatus::ClientApproved])))
            ->orderBy('id')
            ->get()
            ->filter(fn ($i) => self::postAt($i)->between(...$range)) // moved out of this month
            ->sortBy(fn ($i) => self::postAt($i));

        return view('schedule.index', [
            'month' => $month,
            'byDay' => $items->groupBy(fn ($i) => self::postAt($i)->day),
            'brands' => $isCustomer ? collect() : Client::orderBy('name')->when($user->brandLimit(), fn ($q, $ids) => $q->whereIn('id', $ids))->get(['id', 'name']),
            'clientId' => $clientId,
        ]);
    }

    /** When the post goes out: the earliest Facebook / Instagram scheduled time, else the calendar's post date */
    public static function postAt(CalendarItem $item): Carbon
    {
        return $item->task?->contents->flatMap->metaPosts->pluck('publish_at')->filter()->min() ?? $item->post_date;
    }

    /** [label, tailwind classes] for a row: content status, else task, else calendar status */
    public static function status(CalendarItem $item): array
    {
        $content = $item->task?->contents->first();
        if ($content) {
            return [$content->status->label(), $content->status->color()];
        }
        if ($item->task) {
            return [$item->task->isOverdue() ? 'Late' : 'In progress', $item->task->isOverdue() ? 'bg-red-100 text-red-800' : 'bg-sky-100 text-sky-800'];
        }

        return [$item->calendar->status->label(), $item->calendar->status->color()];
    }
}
