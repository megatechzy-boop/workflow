<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\SalaryPayment;
use App\Models\User;
use App\Support\Month;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Pay = monthly salary × paid days ÷ working days (Sundays off; leave and missing days are unpaid) */
class SalaryController extends Controller
{
    public function index(Request $request): View
    {
        $month = Month::from($request);

        return view('office.salary', ['month' => $month, 'rows' => $this->rows($month)]);
    }

    public function setSalary(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['monthly_salary' => ['nullable', 'integer', 'min:0', 'max:10000000']]);
        $user->update($data);

        return back()->with('status', "Salary saved for {$user->name}.");
    }

    public function pay(Request $request, User $user): RedirectResponse
    {
        $month = Month::from($request);
        $row = collect($this->rows($month))->firstWhere('user.id', $user->id);
        abort_unless($row && $row['payable'] > 0, 422);

        SalaryPayment::updateOrCreate(['user_id' => $user->id, 'month' => $month], ['amount' => $row['payable'], 'paid_at' => now()]);

        return back()->with('status', "Salary marked paid for {$user->name}.");
    }

    public function unpay(Request $request, User $user): RedirectResponse
    {
        SalaryPayment::where('user_id', $user->id)->whereDate('month', Month::from($request))->delete();

        return back()->with('status', "Payment removed for {$user->name}.");
    }

    private function rows(CarbonImmutable $month): array
    {
        $working = Attendance::workingDays($month);
        $paidDays = Attendance::paidDays($month);
        $payments = SalaryPayment::whereDate('month', $month)->get()->keyBy('user_id');

        return User::staff()->map(fn (User $u) => [
            'user' => $u,
            'days' => $paidDays[$u->id] ?? 0,
            'working' => $working,
            'payable' => (int) round(($u->monthly_salary ?? 0) * min($paidDays[$u->id] ?? 0, $working) / max($working, 1)),
            'payment' => $payments[$u->id] ?? null,
        ])->all();
    }
}
