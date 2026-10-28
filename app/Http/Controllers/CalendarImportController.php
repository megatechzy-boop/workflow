<?php

namespace App\Http\Controllers;

use App\Models\Calendar;
use App\Support\SheetImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Paste rows from the team's Excel / Google Sheet into a draft calendar (all rows or none) */
class CalendarImportController extends Controller
{
    public function create(Calendar $calendar): View
    {
        Gate::authorize('update', $calendar);

        return view('calendars.import', compact('calendar'));
    }

    public function store(Request $request, Calendar $calendar): RedirectResponse
    {
        Gate::authorize('update', $calendar);

        $text = $request->validate(['rows' => ['required', 'string', 'max:200000']])['rows'];
        [$rows, $errors] = SheetImport::parse($text, $calendar->month);

        if ($errors || ! $rows) {
            return back()->withInput()->withErrors(['rows' => $errors ?: ['No rows found to import.']]);
        }

        DB::transaction(fn () => collect($rows)->each(fn ($row) => $calendar->items()->create($row)));

        return redirect()->route('calendars.show', $calendar)->with('status', count($rows).' row(s) imported.');
    }
}
