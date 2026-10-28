<?php

namespace App\Http\Controllers;

use App\Models\Calendar;
use App\Models\CalendarItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CalendarItemController extends Controller
{
    public function create(Calendar $calendar): View
    {
        Gate::authorize('update', $calendar);

        return view('calendars.item-form', ['calendar' => $calendar, 'item' => new CalendarItem]);
    }

    public function store(Request $request, Calendar $calendar): RedirectResponse
    {
        Gate::authorize('update', $calendar);

        $calendar->items()->create($this->validated($request, $calendar));

        return redirect()->route('calendars.show', $calendar)->with('status', 'Post add ho gaya.');
    }

    public function edit(Calendar $calendar, CalendarItem $item): View
    {
        Gate::authorize('update', $calendar);

        return view('calendars.item-form', compact('calendar', 'item'));
    }

    public function update(Request $request, Calendar $calendar, CalendarItem $item): RedirectResponse
    {
        Gate::authorize('update', $calendar);

        // Row badli => Customer ko ye row dobara check karni hogi (pichla comment reference ke liye rehta hai)
        $item->update([...$this->validated($request, $calendar), 'client_status' => null]);

        return redirect()->route('calendars.show', $calendar)->with('status', 'Post update ho gaya.');
    }

    public function destroy(Calendar $calendar, CalendarItem $item): RedirectResponse
    {
        Gate::authorize('update', $calendar);

        $item->delete();

        return redirect()->route('calendars.show', $calendar)->with('status', 'Post hata diya.');
    }

    private function validated(Request $request, Calendar $calendar): array
    {
        $month = $calendar->month;

        return $request->validate([
            // Post date usi calendar ke mahine mein honi chahiye
            'post_date' => ['required', 'date', 'after_or_equal:'.$month->toDateString(), 'before_or_equal:'.$month->copy()->endOfMonth()->toDateString()],
            'post_types' => ['required', 'array', 'min:1'],
            'post_types.*' => ['distinct', Rule::in(array_keys(CalendarItem::typeOptions()))],
            'creative_copy' => ['nullable', 'string', 'max:5000'],
            'visual_brief' => ['nullable', 'string', 'max:5000'],
            'brief' => ['nullable', 'string', 'max:5000'],
            'reference_link' => ['nullable', 'url', 'max:500'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ], [
            'post_date.after_or_equal' => 'Date '.$month->format('F Y').' ke andar honi chahiye.',
            'post_date.before_or_equal' => 'Date '.$month->format('F Y').' ke andar honi chahiye.',
        ]);
    }
}
