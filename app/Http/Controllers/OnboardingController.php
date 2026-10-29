<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\Client;
use App\Models\User;
use App\Support\Notify;
use App\Support\OnboardingForm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Each client gets a private onboarding link (no login); the answers become the client's brief for the team */
class OnboardingController extends Controller
{
    public function makeLink(Client $client): RedirectResponse
    {
        $client->update(['onboarding_token' => $client->onboarding_token ?? Str::random(32)]);

        return back()->with('status', 'Onboarding link ready.');
    }

    public function form(string $token): View
    {
        $client = Client::where('onboarding_token', $token)->firstOrFail();

        return view('onboarding.form', ['client' => $client, 'answers' => $client->onboarding ?? []]);
    }

    public function submit(Request $request, string $token): RedirectResponse
    {
        $client = Client::where('onboarding_token', $token)->firstOrFail();
        $answers = collect($request->validate(OnboardingForm::rules(), ['consent.accepted' => 'Please confirm to submit.']))
            ->except('consent')
            ->filter(fn ($v) => filled($v))
            ->all();
        $first = $client->onboarded_at === null;
        $client->update(['onboarding' => $answers, 'onboarded_at' => now()]);

        Notify::send(
            User::where('is_active', true)->get()->filter(fn (User $u) => $u->hasPermission(Permission::ManageClients)),
            $first ? 'Onboarding form filled' : 'Onboarding form updated', $client->name, route('clients.brief', $client)
        );

        return redirect()->route('onboarding.thanks');
    }

    public function thanks(): View
    {
        return view('leads.thanks', ['title' => 'Thank you', 'message' => 'Your onboarding details are saved. Our team will review them and get started.']);
    }

    /** The answers, for people who plan the client's content */
    public function brief(Request $request, Client $client): View
    {
        $user = $request->user();
        abort_unless($user->hasPermission(Permission::ManageClients) || $user->hasPermission(Permission::ManageCalendars), 403);

        return view('onboarding.brief', ['client' => $client, 'answers' => $client->onboarding ?? []]);
    }
}
