<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Calendar;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TaskController extends Controller
{
    /** Team Member: apne tasks. Admin/Manager: sabke, member filter ke saath. */
    public function index(Request $request): View
    {
        $user = $request->user();
        $canSeeAll = $user->hasRole('admin', 'manager');
        abort_unless($canSeeAll || $user->role === Role::TeamMember, 403);

        $tasks = Task::query()
            ->with(['item.calendar.client', 'assignee'])
            ->when(! $canSeeAll, fn ($q) => $q->where('assigned_to', $user->id))
            ->when($canSeeAll && $request->member, fn ($q, $id) => $q->where('assigned_to', $id))
            ->when($request->status ?? 'pending', fn ($q, $s) => $s === 'all' ? $q : $q->where('status', $s))
            ->orderBy('deadline')
            ->paginate(30)
            ->withQueryString();

        return view('tasks.index', [
            'tasks' => $tasks,
            'members' => $canSeeAll ? User::teamMembers() : collect(),
        ]);
    }

    /** Task page: brief + har post type ka upload/status */
    public function show(Request $request, Task $task): View
    {
        $user = $request->user();
        abort_unless($task->assigned_to === $user->id || $user->hasRole('admin', 'manager', 'approver'), 403);

        $task->load(['item.calendar.client', 'assignee', 'contents.approver']);

        return view('tasks.show', [
            'task' => $task,
            'approvers' => User::where('role', Role::Approver)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Approved calendar ke har post par member chuno; deadline apne aap */
    public function assign(Request $request, Calendar $calendar): RedirectResponse
    {
        Gate::authorize('assign', $calendar);

        $data = $request->validate([
            'assign' => ['array'],
            'assign.*' => ['nullable', Rule::in(User::teamMembers()->pluck('id')->all())],
        ]);

        DB::transaction(function () use ($data, $calendar, $request) {
            // Sirf isi calendar ke items par chalte hain, toh doosre calendar ka item ID kaam nahi karega
            foreach ($calendar->items()->with('task')->get() as $item) {
                $memberId = $data['assign'][$item->id] ?? null;
                if (! $memberId) {
                    continue; // khaali chhoda = abhi assign nahi
                }

                if ($item->task) {
                    $item->task->update(['assigned_to' => $memberId]); // reassign; deadline wahi
                } else {
                    $item->task()->create([
                        'assigned_to' => $memberId,
                        'assigned_by' => $request->user()->id,
                        'deadline' => Task::deadlineFor($item->post_date),
                    ]);
                }
            }
        });

        return back()->with('status', 'Tasks assign ho gaye.');
    }
}
