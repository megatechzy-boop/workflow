<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\Client;
use App\Models\Lead;
use App\Models\User;
use App\Support\Notify;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Public enquiry form (no login) => Leads list for people who manage clients */
class LeadController extends Controller
{
    public function form(): View
    {
        return view('leads.enquiry');
    }

    public function submit(Request $request): RedirectResponse
    {
        // Honeypot: real people never fill the hidden "website" field
        if (filled($request->input('website'))) {
            return redirect()->route('enquiry.thanks');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'business' => ['nullable', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9 +()-]{7,}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'needs' => ['required', 'array', 'min:1'],
            'needs.*' => [Rule::in(Lead::NEEDS)],
            'goal' => ['required', 'string', 'max:2000'],
            'budget' => ['nullable', Rule::in(Lead::BUDGETS)],
            'timeline' => ['nullable', Rule::in(Lead::TIMELINES)],
            'source' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], ['phone.regex' => 'Enter a valid phone / WhatsApp number.', 'needs.required' => 'Pick at least one option.']);

        $lead = Lead::create($data);

        Notify::send(
            User::where('is_active', true)->get()->filter(fn (User $u) => $u->hasPermission(Permission::ManageClients)),
            'New enquiry', $lead->name.($lead->business ? " ({$lead->business})" : '').' · '.implode(', ', $lead->needs),
            route('leads.index')
        );

        return redirect()->route('enquiry.thanks');
    }

    public function thanks(): View
    {
        return view('leads.thanks');
    }

    public function index(Request $request): View
    {
        $leads = Lead::with('client')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()->paginate(30)->withQueryString();

        return view('leads.index', ['leads' => $leads, 'counts' => Lead::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')]);
    }

    public function update(Request $request, Lead $lead): RedirectResponse
    {
        $lead->update($request->validate([
            'status' => ['required', Rule::in(array_keys(Lead::STATUSES))],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]));

        return back()->with('status', 'Lead updated.');
    }

    /** Won: make the client (brand) from the lead and open it to pick a package */
    public function convert(Lead $lead): RedirectResponse
    {
        $client = $lead->client ?? Client::create([
            'name' => $lead->business ?: $lead->name,
            'contact_email' => $lead->email,
            'phone' => $lead->phone,
            'notes' => trim("Contact: {$lead->name}\nNeeds: ".implode(', ', $lead->needs)."\nGoal: {$lead->goal}"),
        ]);
        $lead->update(['status' => 'won', 'client_id' => $client->id]);

        return redirect()->route('clients.edit', $client)->with('status', 'Client created from the enquiry. Choose a package.');
    }
}
