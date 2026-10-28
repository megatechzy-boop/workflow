<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\PointEntry;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class LeaderboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_if($user->role === Role::Client, 403);

        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->month)
            ? Carbon::createFromFormat('Y-m-d', $request->month.'-01')->startOfDay()
            : now()->startOfMonth();
        $range = [$month, $month->copy()->endOfMonth()];

        $totals = PointEntry::whereBetween('created_at', $range)
            ->selectRaw('user_id, SUM(points) as total, COUNT(*) as entries')
            ->groupBy('user_id')
            ->get()->keyBy('user_id');

        // Show every active member, including those with 0 points
        $board = User::creators()
            ->merge(User::whereIn('id', $totals->keys())->get(['id', 'name', 'role']))
            ->unique('id')
            ->map(fn ($u) => (object) ['user' => $u, 'total' => (int) ($totals[$u->id]->total ?? 0)])
            ->sortByDesc('total')->values();

        // History: members see their own; view-all users can pick anyone (?user=)
        $historyUser = $user->hasPermission(Permission::ViewAllTasks) && $request->user
            ? User::findOrFail($request->user)
            : $user;
        $history = PointEntry::with(['task.item.calendar.client', 'content'])
            ->where('user_id', $historyUser->id)
            ->whereBetween('created_at', $range)
            ->latest()
            ->get();

        return view('leaderboard.index', compact('board', 'history', 'historyUser', 'month'));
    }
}
