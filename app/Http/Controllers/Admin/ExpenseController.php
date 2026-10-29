<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\SalaryPayment;
use App\Support\Month;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Office expenses, plus the month's profit: bills received − salaries paid − expenses */
class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        $month = Month::from($request);
        $expenses = Expense::whereBetween('date', [$month, $month->endOfMonth()])->orderByDesc('date')->orderByDesc('id')->get();
        $received = (int) Invoice::whereDate('month', $month)->whereNotNull('paid_at')->sum('amount');
        $salaries = (int) SalaryPayment::whereDate('month', $month)->sum('amount');

        return view('office.expenses', [
            'month' => $month,
            'expenses' => $expenses,
            'summary' => ['received' => $received, 'salaries' => $salaries, 'expenses' => (int) $expenses->sum('amount')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(array_keys(Expense::CATEGORIES))],
            'amount' => ['required', 'integer', 'min:1', 'max:100000000'],
        ]);
        Expense::create($data + ['created_by' => $request->user()->id]);

        return back()->with('status', 'Expense added.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $expense->delete();

        return back()->with('status', 'Expense deleted.');
    }
}
