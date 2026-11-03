<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ReviewTemplate;
use App\Support\ReviewTemplates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Admin: review reply templates per client (and the default set new clients get a copy of) */
class ReviewTemplateController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'client' => ['nullable', 'integer', 'exists:clients,id'],
            'group' => ['nullable', Rule::in(array_keys(ReviewTemplates::GROUPS))],
            'language' => ['nullable', Rule::in(array_keys(ReviewTemplates::LANGUAGES))],
        ]);
        $query = ReviewTemplate::where('client_id', $filters['client'] ?? null);

        return view('review-templates.index', [
            'templates' => (clone $query)->when($filters['group'] ?? null, fn ($q, $g) => $q->where('rating_group', $g))
                ->when($filters['language'] ?? null, fn ($q, $l) => $q->where('language', $l))
                ->orderBy('rating_group')->orderBy('language')->orderBy('id')->get(),
            // active templates per group + language: rotation needs 4+ to never repeat the last 3
            'counts' => $query->where('is_active', true)->selectRaw('rating_group, language, count(*) as n')->groupBy('rating_group', 'language')
                ->get()->mapWithKeys(fn ($r) => [$r->rating_group.'.'.$r->language => $r->n]),
            'clients' => Client::orderBy('name')->pluck('name', 'id'),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): View
    {
        return $this->form(new ReviewTemplate(['client_id' => $request->integer('client') ?: null, 'rating_group' => 'positive', 'language' => 'en', 'is_active' => true]));
    }

    public function store(Request $request): RedirectResponse
    {
        $template = ReviewTemplate::create($this->validated($request));

        return redirect()->route('admin.review-templates.index', array_filter(['client' => $template->client_id]))->with('status', 'Template added.');
    }

    public function edit(ReviewTemplate $reviewTemplate): View
    {
        return $this->form($reviewTemplate);
    }

    public function update(Request $request, ReviewTemplate $reviewTemplate): RedirectResponse
    {
        $reviewTemplate->update($this->validated($request));

        return redirect()->route('admin.review-templates.index', array_filter(['client' => $reviewTemplate->client_id]))->with('status', 'Template saved.');
    }

    /** Copy, then open the copy to change it */
    public function duplicate(ReviewTemplate $reviewTemplate): RedirectResponse
    {
        $copy = $reviewTemplate->replicate();
        $copy->save();

        return redirect()->route('admin.review-templates.edit', $copy)->with('status', 'Copy made. Change it and save.');
    }

    private function form(ReviewTemplate $template): View
    {
        $client = $template->client_id ? Client::with('gbpLocation')->find($template->client_id) : null;

        return view('review-templates.form', [
            'template' => $template,
            'clients' => Client::orderBy('name')->pluck('name', 'id'),
            'sample' => [
                'business' => $client?->gbpLocation?->title ?? $client?->name ?? 'Acme Clinic',
                'phone' => $client?->gbpLocation?->phone ?? '98765 43210',
                'city' => $client?->gbpLocation?->city ?? 'Pune',
                'healthcare' => (bool) $client?->is_healthcare,
            ],
        ]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'client_id' => ['nullable', 'exists:clients,id'],
            'rating_group' => ['required', Rule::in(array_keys(ReviewTemplates::GROUPS))],
            'language' => ['required', Rule::in(array_keys(ReviewTemplates::LANGUAGES))],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        return $data + ['is_active' => $request->boolean('is_active')];
    }
}
