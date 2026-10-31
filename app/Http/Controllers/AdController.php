<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\Client;
use App\Models\MetaAd;
use App\Support\MetaAds;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/** Ad Setup: goal, audience, budget and creative → a paused ad on the client's ad account; Start makes it live */
class AdController extends Controller
{
    public function index(Request $request): View
    {
        $user = $this->authorizeUser($request);

        $ads = MetaAd::with('client', 'adAccount', 'audience')
            ->when($user->brandLimit(), fn ($q, $ids) => $q->whereIn('client_id', $ids))
            ->latest()->paginate(30);

        // A/B tests: results of both versions so far (cached 3 hours)
        $token = \App\Models\MetaConnection::token();
        $ab = $token ? $ads->filter(fn (MetaAd $ad) => $ad->ad_b_id)->mapWithKeys(fn (MetaAd $ad) => [$ad->id => collect([
            'A' => \App\Support\MetaInsights::ad($token, $ad->ad_id, $ad->goal, $ad->start_on, $ad->end_on->min(today())),
            'B' => \App\Support\MetaInsights::ad($token, $ad->ad_b_id, $ad->goal, $ad->start_on, $ad->end_on->min(today())),
        ])]) : collect();

        return view('ads.index', ['ads' => $ads, 'ab' => $ab]);
    }

    public function create(Request $request): View
    {
        $clients = $this->clients($this->authorizeUser($request));

        return view('ads.create', ['clients' => $clients,
            'audiences' => \App\Models\MetaAudience::whereIn('client_id', $clients->pluck('id'))->where('status', 'ready')->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->authorizeUser($request);
        $data = $request->validate([
            'client_id' => ['required', Rule::in($this->clients($user)->pluck('id')->all())],
            'name' => ['required', 'string', 'max:100'],
            'goal' => ['required', Rule::in(array_keys(MetaAd::GOALS))],
            'daily_budget' => ['required', 'integer', 'min:100', 'max:100000'],
            'start_on' => ['required', 'date', 'after_or_equal:today'],
            'end_on' => ['required', 'date', 'after:start_on'],
            'city' => ['nullable', 'string', 'max:80'],
            'radius_km' => ['nullable', 'integer', 'min:1', 'max:80'],
            'age_min' => ['required', 'integer', 'min:18', 'max:65'],
            'age_max' => ['required', 'integer', 'min:18', 'max:65', 'gte:age_min'],
            'gender' => ['required', Rule::in(['all', 'male', 'female'])],
            'interests' => ['nullable', 'string', 'max:255'],
            'primary_text' => ['required', 'string', 'max:2000'],
            'headline' => ['nullable', 'string', 'max:100'],
            'url' => ['nullable', 'required_if:goal,website', 'url:https,http', 'max:500'],
            'phone' => ['nullable', 'required_if:goal,calls', 'regex:/^\+?[0-9 ]{10,15}$/'],
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:20480'],
            'meta_audience_id' => ['nullable', Rule::exists('meta_audiences', 'id')->where('client_id', $request->client_id)->where('status', 'ready')],
            // A/B test: version B changes the image and/or the text; both versions get half the budget
            'ab_test' => ['nullable', 'boolean'],
            'image_b' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:20480'],
            'primary_text_b' => ['nullable', 'string', 'max:2000'],
            'headline_b' => ['nullable', 'string', 'max:100'],
        ], ['phone.regex' => 'Enter the phone with country code, e.g. +91 98765 43210.']);
        $ab = $request->boolean('ab_test');
        if ($ab && ! $request->hasFile('image_b') && blank($data['primary_text_b'] ?? null)) {
            return back()->withInput()->withErrors(['image_b' => 'For an A/B test give version B a different image or text.']);
        }
        if ($ab && $data['daily_budget'] < 200) {
            return back()->withInput()->withErrors(['daily_budget' => 'An A/B test needs at least ₹200 per day (₹100 for each version).']);
        }

        $client = Client::findOrFail($data['client_id']);
        $ad = MetaAd::create(['image_path' => $request->file('image')->store('ads', 'local'), 'meta_ad_account_id' => $client->meta_ad_account_id,
            'phone' => isset($data['phone']) ? preg_replace('/\s+/', '', $data['phone']) : null, 'created_by' => $user->id,
            'image_b_path' => $ab && $request->hasFile('image_b') ? $request->file('image_b')->store('ads', 'local') : null,
            'primary_text_b' => $ab ? ($data['primary_text_b'] ?: null) : null, 'headline_b' => $ab ? ($data['headline_b'] ?? null) : null]
            + collect($data)->except('image', 'phone', 'ab_test', 'image_b', 'primary_text_b', 'headline_b')->all());

        try {
            MetaAds::create($ad);
        } catch (Throwable $e) {
            $ad->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000)]);

            return redirect()->route('ads.index')->with('status', 'Meta did not accept the ad: '.$e->getMessage());
        }

        return redirect()->route('ads.index')->with('status', 'Ad created (paused). Check it, then press Start.');
    }

    public function status(Request $request, MetaAd $ad): RedirectResponse
    {
        $user = $this->authorizeUser($request);
        abort_unless($user->canSeeBrand($ad->client_id) && $ad->ad_id, 403);
        $status = $request->validate(['status' => ['required', Rule::in(['active', 'paused'])]])['status'];

        try {
            MetaAds::setStatus($ad, $status);
        } catch (Throwable $e) {
            return back()->with('status', 'Meta said: '.$e->getMessage());
        }

        return back()->with('status', $status === 'active' ? 'Ad is live. Spending starts now.' : 'Ad paused.');
    }

    /** Same people who publish posts */
    private function authorizeUser(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasPermission(Permission::PublishContent), 403);

        return $user;
    }

    private function clients($user)
    {
        return Client::readyForAds($user);
    }
}
