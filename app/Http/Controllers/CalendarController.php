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
            // Client ko sirf apne, Approver-OK ke baad wale calendars
            ->when($user->role === Role::Client, fn ($q) => $q
                ->where('client_id', $user->client_id)
                ->whereIn('status', [CalendarStatus::ApproverApproved, CalendarStatus::ClientApproved]))
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
            return redirect()->route('calendars.show', $exists)->with('status', 'Is client ka is mahine ka calendar pehle se hai.');
        }

        $calendar = Calendar::create([
            'client_id' => $data['client_id'],
            'month' => $month,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('calendars.show', $calendar)->with('status', 'Calendar ban gaya. Ab posts add karo.');
    }

    public function show(Calendar $calendar): View
    {
        Gate::authorize('view', $calendar);

        $calendar->load(['client', 'creator', 'items.task.assignee', 'reviews.user']);
        $user = request()->user();

        return view('calendars.show', [
            'calendar' => $calendar,
            // Tasks columns: approved calendar par, client ko chhod kar sabko
            'showTasks' => $calendar->status === CalendarStatus::ClientApproved && $user->role !== Role::Client,
            // Saare Designers/Editors; view har post ke type ke hisaab se filter karta hai
            'members' => $user->can('assign', $calendar) ? User::creators() : collect(),
        ]);
    }

    public function destroy(Calendar $calendar): RedirectResponse
    {
        Gate::authorize('delete', $calendar);

        $calendar->delete();

        return redirect()->route('calendars.index')->with('status', 'Calendar delete ho gaya.');
    }

    public function submit(Request $request, Calendar $calendar): RedirectResponse
    {
        Gate::authorize('submit', $calendar);

        // Pehle Admin/Ops Manager approve karega, phir Customer
        DB::transaction(function () use ($request, $calendar) {
            $calendar->update(['status' => CalendarStatus::Submitted]);
            $calendar->reviews()->create(['user_id' => $request->user()->id, 'action' => 'submitted']);
        });

        Notify::send(User::withPermission(Permission::ApproveCalendar), 'Calendar approval ke liye',
            $this->name($calendar).' ka calendar review karein.', route('calendars.show', $calendar));

        return back()->with('status', 'Calendar Ops Manager ko bhej diya.');
    }

    /** Ops Manager poore calendar ko approve / revision karta hai */
    public function review(Request $request, Calendar $calendar): RedirectResponse
    {
        Gate::authorize('review', $calendar);

        $data = $request->validate([
            'action' => ['required', Rule::in(['approved', 'revision'])],
            'comment' => ['required_if:action,revision', 'nullable', 'string', 'max:5000'],
        ], ['comment.required_if' => 'Revision ke liye comment zaroori hai.']);

        $approved = $data['action'] === 'approved';
        $this->transition($request, $calendar, $approved ? CalendarStatus::ApproverApproved : CalendarStatus::Draft, $data['action'], $data['comment'] ?? null);

        return back()->with('status', $approved ? 'Calendar Customer ko bhej diya.' : 'Revision ke liye wapas bhej diya.');
    }

    /**
     * Customer har row alag check karta hai. Saari rows check hone par:
     * sab approve => calendar approved (+ auto-assign), koi bhi revision => SMM ke paas wapas.
     */
    public function reviewItem(Request $request, Calendar $calendar, CalendarItem $item): RedirectResponse
    {
        Gate::authorize('reviewItems', $calendar);

        $data = $request->validate([
            'action' => ['required', Rule::in(['approved', 'revision'])],
            'comment' => ['required_if:action,revision', 'nullable', 'string', 'max:5000'],
        ], ['comment.required_if' => 'Revision ke liye comment zaroori hai.']);

        $item->update(['client_status' => $data['action'], 'client_comment' => $data['comment'] ?? null]);

        $items = $calendar->items()->get();
        if ($items->whereNull('client_status')->isNotEmpty()) {
            return back()->with('status', 'Row '.($data['action'] === 'approved' ? 'approve' : 'revision').' ho gayi. Baaki rows: '.$items->whereNull('client_status')->count());
        }

        $revisions = $items->where('client_status', 'revision');
        if ($revisions->isEmpty()) {
            $this->transition($request, $calendar, CalendarStatus::ClientApproved, 'approved', null);

            return back()->with('status', 'Saari rows approve! Calendar approved ✔');
        }

        $summary = $revisions->map(fn ($i) => $i->post_date->format('d M').': '.$i->client_comment)->implode("\n");
        $this->transition($request, $calendar, CalendarStatus::Draft, 'revision', $summary);

        return back()->with('status', $revisions->count().' rows par revision. Calendar SMM ko wapas bhej diya.');
    }

    /** Status badlo + history + (approve par) auto-assign + notifications */
    private function transition(Request $request, Calendar $calendar, CalendarStatus $next, string $action, ?string $comment): void
    {
        $autoTasks = DB::transaction(function () use ($request, $calendar, $next, $action, $comment) {
            $calendar->update(['status' => $next]);
            $calendar->reviews()->create(['user_id' => $request->user()->id, 'action' => $action, 'comment' => $comment]);

            // Customer ne approve kiya => tasks apne aap ban jaate hain
            return $next === CalendarStatus::ClientApproved ? Task::autoAssign($calendar, $request->user()->id) : [];
        });

        $url = route('calendars.show', $calendar);
        $name = $this->name($calendar);
        match ($next) {
            CalendarStatus::Draft => Notify::send([$calendar->creator], 'Calendar par revision', "$name:\n$comment", $url),
            CalendarStatus::ApproverApproved => $this->notifyCustomers($calendar),
            CalendarStatus::ClientApproved => Notify::send(
                User::withPermission(Permission::AssignTasks)->push($calendar->creator),
                'Calendar approved ✔', "$name customer ne approve kar diya. ".count($autoTasks).' tasks auto-assign ho gaye, chahein toh badlein.', $url),
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
            'Calendar aapke approval ke liye', $this->name($calendar).' ka content calendar review karein.',
            route('calendars.show', $calendar));
    }

    private function name(Calendar $calendar): string
    {
        return $calendar->client->name.' · '.$calendar->month->format('F Y');
    }
}
