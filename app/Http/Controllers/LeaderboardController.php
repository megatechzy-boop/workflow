<?php

namespace App\Http\Controllers;

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

        // Sab active members dikhein, jinke 0 points hain woh bhi
        $board = User::creators()
            ->merge(User::whereIn('id', $totals->keys())->get(['id', 'name', 'role']))
            ->unique('id')
            ->map(fn ($u) => (object) ['user' => $u, 'total' => (int) ($totals[$u->id]->total ?? 0)])
            ->sortByDesc('total')->values();

        // History: member ko apni, Admin/Ops/SMM kisi ki bhi (?user=)
        $historyUser = $user->role->isPlanner() && $request->user
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
