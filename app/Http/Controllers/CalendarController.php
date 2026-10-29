<?php

namespace App\Http\Controllers;

use App\Enums\CalendarStatus;
use App\Enums\Role;
use App\Models\Calendar;
use App\Models\CalendarItem;
use App\Models\Client;
use App\Enums\Permission;
use App\Models\Task;
use App\Models\User;
use App\Support\Notify;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $calendars = Calendar::query()
            ->with('client')
            ->withCount('items')
            // Customers only see their own calendars, after submission
            ->when($user->role === Role::Client, fn ($q) => $q
                ->where('client_id', $user->client_id)
                ->whereIn('status', [CalendarStatus::ApproverApproved, CalendarStatus::ClientApproved]))
            ->tap(User::draftFilter($user))
            // Designers/Editors: only calendars with a post assigned to them
            ->when($user->seesOnlyOwnRows(), fn ($q) => $q->whereHas('items.task', fn ($t) => $t->where('assigned_to', $user->id)))
            ->when($request->client_id, fn ($q, $id) => $q->where('client_id', $id))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('month')
            ->paginate(20)
            ->withQueryString();

        return view('calendars.index', [
            'calendars' => $calendars,
            'clients' => $user->role === Role::Client ? collect() : Client::orderBy('name')->get(),
            'statuses' => CalendarStatus::cases(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Calendar::class);

        return view('calendars.create', ['clients' => Client::where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Calendar::class);

        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'month' => ['required', 'date_format:Y-m'],
        ]);
        $month = Carbon::createFromFormat('Y-m-d', $data['month'].'-01')->startOfDay();

        $exists = Calendar::where('client_id', $data['client_id'])->whereDate('month', $month)->first();
        if ($exists) {
            return redirect()->route('calendars.show', $exists)->with('status', 'This brand already has a calendar for this month.');
        }

        $calendar = Calendar::create([
            'client_id' => $data['client_id'],
            'month' => $month,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('calendars.show', $calendar)->with('status', 'Calendar created. Now add posts.');
    }

    public function show(Calendar $calendar): View|RedirectResponse
    {
        // Not allowed: back to the list with a popup that says why (instead of a 403 page)
        if (Gate::denies('view', $calendar)) {
            $user = request()->user();
            $why = match (true) {
                $calendar->status === CalendarStatus::Draft && $user->role !== Role::Client => 'This calendar is still a draft. Only the person who made it, Admin and Ops Manager can open it.',
                $user->seesOnlyOwnRows() => 'You have no posts in this calendar. You can open calendars that have a post assigned to you.',
                default => 'You cannot open this calendar.',
            };

            return redirect()->route('calendars.index')->with('popup', $why);
        }

        $calendar->load(['client.package', 'creator', 'items.task.assignee', 'items.assets', 'reviews.user']);
        $user = request()->user();
        if ($user->seesOnlyOwnRows()) {
            $calendar->setRelation('items', $calendar->items->filter(fn ($i) => $i->task?->assigned_to === $user->id)->values());
        }

        return view('calendars.show', [
            'calendar' => $calendar,
            // Task columns: on approved calendars, for everyone except Customers
            'showTasks' => $calendar->status === CalendarStatus::ClientApproved && $user->role !== Role::Client,
            // All Designers/Editors; the view filters them per post format
            'members' => $user->can('assign', $calendar) ? User::creators() : collect(),
        ]);
    }

    public function destroy(Calendar $calendar): RedirectResponse
    {
        Gate::authorize('delete', $calendar);

        $calendar->delete();

        return redirect()->route('calendars.index')->with('status', 'Calendar deleted.');
    }

    public function submit(Request $request, Calendar $calendar): RedirectResponse
    {
        Gate::authorize('submit', $calendar);

        // Tasks, deadlines and the schedule all need a date
        $undated = $calendar->items()->whereNull('post_date')->count();
        if ($undated) {
            return back()->with('status', "Add a date to {$undated} ".Str::plural('row', $undated).' before submitting.');
        }

        // First the Admin/Ops Manager approves, then the Customer
        DB::transaction(function () use ($request, $calendar) {
            $calendar->update(['status' => CalendarStatus::Submitted]);
            $calendar->reviews()->create(['user_id' => $request->user()->id, 'action' => 'submitted']);
        });

        Notify::send(User::withPermission(Permission::ApproveCalendar), 'Calendar needs approval',
            $this->name($calendar).' calendar is waiting for your review.', route('calendars.show', $calendar));

        return back()->with('status', 'Calendar sent to the Ops Manager.');
    }

    /** The Ops Manager approves or sends back the whole calendar */
    public function review(Request $request, Calendar $calendar): RedirectResponse
    {
        Gate::authorize('review', $calendar);

        $data = $request->validate([
            'action' => ['required', Rule::in(['approved', 'revision'])],
            'comment' => ['required_if:action,revision', 'nullable', 'string', 'max:5000'],
        ], ['comment.required_if' => 'A comment is required for a revision.']);

        $approved = $data['action'] === 'approved';
        $this->transition($request, $calendar, $approved ? CalendarStatus::ApproverApproved : CalendarStatus::Draft, $data['action'], $data['comment'] ?? null);

        return back()->with('status', $approved ? 'Calendar sent to the Customer.' : 'Sent back for revision.');
    }

    /**
     * The Customer checks every row. Once all rows are checked:
     * all approved => calendar approved (+ auto-assign), any revision => back to the SMM.
     */
    public function reviewItem(Request $request, Calendar $calendar, CalendarItem $item): RedirectResponse
    {
        Gate::authorize('reviewItems', $calendar);

        $data = $request->validate([
            'action' => ['required', Rule::in(['approved', 'revision'])],
            'comment' => ['required_if:action,revision', 'nullable', 'string', 'max:5000'],
        ], ['comment.required_if' => 'A comment is required for a revision.']);

        $item->update(['client_status' => $data['action'], 'client_comment' => $data['comment'] ?? null]);

        $items = $calendar->items()->get();
        if ($items->whereNull('client_status')->isNotEmpty()) {
            return back()->with('status', 'Row marked '.($data['action'] === 'approved' ? 'approved' : 'for revision').'. Rows left: '.$items->whereNull('client_status')->count());
        }

        $revisions = $items->where('client_status', 'revision');
        if ($revisions->isEmpty()) {
            $this->transition($request, $calendar, CalendarStatus::ClientApproved, 'approved', null);

            return back()->with('status', 'All rows approved! Calendar approved.');
        }

        $summary = $revisions->map(fn ($i) => $i->post_date->format('d M').': '.$i->client_comment)->implode("\n");
        $this->transition($request, $calendar, CalendarStatus::Draft, 'revision', $summary);

        return back()->with('status', $revisions->count().' row(s) need revision. Calendar sent back to the SMM.');
    }

    /** Change status + history + (on approval) auto-assign + notifications */
    private function transition(Request $request, Calendar $calendar, CalendarStatus $next, string $action, ?string $comment): void
    {
        $autoTasks = DB::transaction(function () use ($request, $calendar, $next, $action, $comment) {
            $calendar->update(['status' => $next]);
            $calendar->reviews()->create(['user_id' => $request->user()->id, 'action' => $action, 'comment' => $comment]);

            // Customer approved => tasks are created automatically
            return $next === CalendarStatus::ClientApproved ? Task::autoAssign($calendar, $request->user()->id) : [];
        });

        $url = route('calendars.show', $calendar);
        $name = $this->name($calendar);
        match ($next) {
            CalendarStatus::Draft => Notify::send([$calendar->creator], 'Calendar needs changes', "$name:\n$comment", $url),
            CalendarStatus::ApproverApproved => $this->notifyCustomers($calendar),
            CalendarStatus::ClientApproved => Notify::send(
                User::withPermission(Permission::AssignTasks)->push($calendar->creator),
                'Calendar approved', "$name was approved by the customer. ".count($autoTasks).' task(s) were auto-assigned; change them if needed.', $url),
            default => null,
        };
        foreach ($autoTasks as $task) {
            $task->notifyAssignee();
        }
    }

    private function notifyCustomers(Calendar $calendar): void
    {
        Notify::send(
            User::where('role', Role::Client)->where('client_id', $calendar->client_id)->get(),
            'Calendar ready for your approval', $this->name($calendar).' content calendar is ready for your review.',
            route('calendars.show', $calendar));
    }

    private function name(Calendar $calendar): string
    {
        return $calendar->client->name.' · '.$calendar->month->format('F Y');
    }
}
