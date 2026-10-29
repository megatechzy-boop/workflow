<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Attendance;
use App\Models\User;
use App\Support\Month;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Team checks in/out from Home; Admin sees the month and corrects days (half day, leave) */
class AttendanceController extends Controller
{
    public function checkIn(Request $request): RedirectResponse
    {
        abort_if($request->user()->role === Role::Client, 403);

        $day = Attendance::firstOrNew(['user_id' => $request->user()->id, 'date' => today()]);
        $day->check_in_at ??= now();
        $day->status = 'present';
        $day->save();

        return back()->with('status', 'Checked in at '.$day->check_in_at->format('h:i A').'.');
    }

    public function checkOut(Request $request): RedirectResponse
    {
        $day = Attendance::where('user_id', $request->user()->id)->whereDate('date', today())->whereNotNull('check_in_at')->firstOrFail();
        $day->update(['check_out_at' => now()]);

        return back()->with('status', 'Checked out at '.$day->check_out_at->format('h:i A').'.');
    }

    public function index(Request $request): View
    {
        $month = Month::from($request);
        $staff = User::staff();
        $rows = Attendance::whereBetween('date', [$month, $month->endOfMonth()])->get()->groupBy('user_id');
        $member = $staff->firstWhere('id', (int) $request->query('user'));

        return view('office.attendance', [
            'month' => $month,
            'staff' => $staff,
            'rows' => $rows,
            'workingDays' => Attendance::workingDays($month),
            'member' => $member,
            'days' => $member ? ($rows[$member->id] ?? collect())->keyBy(fn ($a) => $a->date->format('Y-m-d')) : collect(),
        ]);
    }

    /** Admin: set one day's status ("" removes the entry = absent) */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'date' => ['required', 'date'],
            'status' => ['nullable', Rule::in(array_keys(Attendance::STATUSES))],
        ]);
        $query = Attendance::where('user_id', $data['user_id'])->whereDate('date', $data['date']);

        if (empty($data['status'])) {
            $query->delete();
        } else {
            $day = $query->first() ?? new Attendance(['user_id' => $data['user_id'], 'date' => $data['date']]);
            $day->fill(['status' => $data['status']])->save();
        }

        return back()->with('status', 'Attendance updated.');
    }
}
