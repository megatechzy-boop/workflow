<?php

namespace App\Http\Controllers;

use App\Enums\Platform;
use App\Enums\PostType;
use App\Models\Calendar;
use App\Models\CalendarItem;
use App\Models\Content;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

        return redirect()->route('calendars.show', $calendar)->with('status', 'Post added.');
    }

    public function edit(Calendar $calendar, CalendarItem $item): View
    {
        Gate::authorize('update', $calendar);

        return view('calendars.item-form', compact('calendar', 'item'));
    }

    public function update(Request $request, Calendar $calendar, CalendarItem $item): RedirectResponse
    {
        Gate::authorize('update', $calendar);

        // Row changed => the Customer has to check it again (the previous comment stays for reference)
        $item->update([...$this->validated($request, $calendar), 'client_status' => null]);

        return redirect()->route('calendars.show', $calendar)->with('status', 'Post updated.');
    }

    public function destroy(Calendar $calendar, CalendarItem $item): RedirectResponse
    {
        Gate::authorize('update', $calendar);

        $item->delete();

        return redirect()->route('calendars.show', $calendar)->with('status', 'Post deleted.');
    }

    private function validated(Request $request, Calendar $calendar): array
    {
        $month = $calendar->month;

        // Form sends one format + platforms => post_types like ["instagram_post", "linkedin_post"]
        if ($request->has('format')) {
            $request->validate([
                'format' => ['required', Rule::enum(PostType::class)],
                'platforms' => ['required', 'array', 'min:1'],
                'platforms.*' => [Rule::enum(Platform::class)],
            ], ['platforms.required' => 'Choose at least one platform.']);
            $request->merge(['post_types' => array_values(array_unique(array_map(
                fn ($p) => $p.'_'.$request->input('format'), $request->input('platforms')
            )))]);
        }

        $data = $request->validate([
            // The post date must fall inside the calendar month
            'post_date' => ['required', 'date', 'after_or_equal:'.$month->toDateString(), 'before_or_equal:'.$month->copy()->endOfMonth()->toDateString()],
            'post_types' => ['required', 'array', 'min:1'],
            'post_types.*' => ['distinct', Rule::in(array_keys(CalendarItem::typeOptions()))],
            'creative_copy' => ['nullable', 'string', 'max:5000'],
            'visual_brief' => ['nullable', 'string', 'max:5000'],
            'brief' => ['nullable', 'string', 'max:5000'],
            'reference_link' => ['nullable', 'url', 'max:500'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ], [
            'post_date.after_or_equal' => 'The date must be in '.$month->format('F Y').'.',
            'post_date.before_or_equal' => 'The date must be in '.$month->format('F Y').'.',
            'post_types.*.in' => 'That format is not available on one of the platforms (e.g. YouTube has no Carousel).',
        ]);

        // One content per row: every platform must share the same format
        if (collect($data['post_types'])->map(fn ($k) => Content::typeOf($k))->unique()->count() > 1) {
            throw ValidationException::withMessages(['post_types' => 'One row can only have one format. Add a separate row for the other format.']);
        }

        return $data;
    }
}
