<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\GbpLocation;
use App\Models\GoogleAccount;
use App\Support\GbpReviews;
use App\Support\GoogleBusiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/** Admin connects Google once; every Business Profile location comes in and is linked to a client here */
class GoogleController extends Controller
{
    public function index(): View
    {
        return view('google.index', [
            'ready' => (bool) config('services.google.client_id'),
            'account' => GoogleAccount::current(),
            'locations' => GbpLocation::with('client')->orderBy('title')->get(),
            'clients' => Client::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function connect(Request $request): RedirectResponse
    {
        abort_unless(config('services.google.client_id'), 422, 'GOOGLE_CLIENT_ID is not set.');
        $state = Str::random(40);
        $request->session()->put('google_state', $state);

        return redirect()->away(GoogleBusiness::loginUrl($state));
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! $request->filled('code') || $request->input('state') !== $request->session()->pull('google_state')) {
            return redirect()->route('admin.google.index')->with('status', 'Google connection was cancelled.');
        }

        try {
            $tokens = GoogleBusiness::exchangeCode($request->input('code'));
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('admin.google.index')->with('status', $e->getMessage());
        }
        GoogleAccount::query()->delete();
        GoogleAccount::create($tokens + ['connected_by' => $request->user()->id]);

        return $this->refresh();
    }

    /** Fetch the locations again (new ones are added; client links are kept) */
    public function refresh(): RedirectResponse
    {
        try {
            $locations = GoogleBusiness::locations(GoogleBusiness::token());
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('admin.google.index')->with('status', 'Google said: '.$e->getMessage());
        }
        foreach ($locations as $location) {
            GbpLocation::updateOrCreate(['location_name' => $location['location_name']], $location);
        }

        return redirect()->route('admin.google.index')->with('status', count($locations).' location(s) found. Link each one to its client below.');
    }

    /** Fetch reviews of every linked location now instead of waiting for gbp:reviews */
    public function syncReviews(): RedirectResponse
    {
        try {
            $new = GbpReviews::syncAll();
        } catch (Throwable $e) {
            report($e);

            return back()->with('status', 'Google said: '.$e->getMessage());
        }
        $failed = GbpLocation::whereNotNull('client_id')->whereNotNull('reviews_error')->count();

        return back()->with('status', $new.' new review(s) fetched.'.($failed ? " $failed location(s) failed: see the error below." : ''));
    }

    public function link(Request $request, GbpLocation $location): RedirectResponse
    {
        $clientId = $request->validate([
            'client_id' => ['nullable', 'exists:clients,id', Rule::unique('gbp_locations', 'client_id')->ignore($location->id)],
        ], ['client_id.unique' => 'That client is already linked to another location.'])['client_id'] ?? null;
        $location->update(['client_id' => $clientId]);

        return back()->with('status', $clientId ? $location->title.' linked to '.$location->client->name.'.' : $location->title.' unlinked.');
    }
}
