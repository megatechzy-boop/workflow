<?php

namespace App\Http\Controllers;

use App\Models\Calendar;
use App\Models\Task;
use App\Models\User;
use App\Support\Notify;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TaskController extends Controller
{
    /** Designer/Editor: apne tasks. Admin/Ops/SMM: sabke, member filter ke saath. */
    public function index(Request $request): View
    {
        $user = $request->user();
        $canSeeAll = $user->role->isPlanner();
        abort_unless($canSeeAll || $user->role->isCreator(), 403);

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
            'members' => $canSeeAll ? User::creators() : collect(),
        ]);
    }

    /** Task page: brief + har post type ka upload/status */
    public function show(Request $request, Task $task): View
    {
        $user = $request->user();
        abort_unless($task->assigned_to === $user->id || $user->role->isPlanner(), 403);

        $task->load(['item.calendar.client', 'assignee', 'contents.approver']);

        return view('tasks.show', ['task' => $task, 'approvers' => User::socialMediaManagers()]);
    }

    /** Approved calendar ke har post par member chuno; deadline apne aap */
    public function assign(Request $request, Calendar $calendar): RedirectResponse
    {
        Gate::authorize('assign', $calendar);

        $creators = User::creators()->keyBy('id');
        $data = $request->validate([
            'assign' => ['array'],
            'assign.*' => ['nullable', Rule::in($creators->keys()->all())],
        ]);

        $newlyAssigned = DB::transaction(function () use ($data, $calendar, $request, $creators) {
            $changed = [];
            // Sirf isi calendar ke items par chalte hain, toh doosre calendar ka item ID kaam nahi karega
            foreach ($calendar->items()->with('task')->get() as $item) {
                $memberId = (int) ($data['assign'][$item->id] ?? 0);
                if (! $memberId || $item->task?->assigned_to === $memberId) {
                    continue; // khaali ya same member = kuch nahi badla
                }
                // Reel => Video Editor, Post/Carousel => Graphic Designer
                if (! in_array($creators[$memberId]->role, $item->assigneeRoles(), true)) {
                    throw ValidationException::withMessages([
                        "assign.{$item->id}" => $creators[$memberId]->name.' ('.$creators[$memberId]->role->label().') ko '.$item->typeLabel().' assign nahi ho sakta.',
                    ]);
                }

                if ($item->task) {
                    $item->task->update(['assigned_to' => $memberId]); // reassign; deadline wahi
                    $changed[] = $item->task;
                } else {
                    $changed[] = $item->task()->create([
                        'assigned_to' => $memberId,
                        'assigned_by' => $request->user()->id,
                        'deadline' => Task::deadlineFor($item->post_date),
                    ]);
                }
            }

            return $changed;
        });

        foreach ($newlyAssigned as $task) {
            Notify::send([$task->assignee], 'Naya task mila',
                $calendar->client->name.' · post date '.$task->item->post_date->format('d M').' · deadline '.$task->deadline->format('d M'),
                route('tasks.show', $task));
        }

        return back()->with('status', 'Tasks assign ho gaye.');
    }
}
