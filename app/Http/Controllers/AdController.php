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

        return view('ads.index', ['ads' => MetaAd::with('client', 'adAccount')
            ->when($user->brandLimit(), fn ($q, $ids) => $q->whereIn('client_id', $ids))
            ->latest()->paginate(30)]);
    }

    public function create(Request $request): View
    {
        return view('ads.create', ['clients' => $this->clients($this->authorizeUser($request))]);
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
        ], ['phone.regex' => 'Enter the phone with country code, e.g. +91 98765 43210.']);

        $client = Client::findOrFail($data['client_id']);
        $ad = MetaAd::create(['image_path' => $request->file('image')->store('ads', 'local'), 'meta_ad_account_id' => $client->meta_ad_account_id,
            'phone' => isset($data['phone']) ? preg_replace('/\s+/', '', $data['phone']) : null, 'created_by' => $user->id]
            + collect($data)->except('image', 'phone')->all());

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

    /** Clients ready for ads: a Page and an ad account, within the user's brands */
    private function clients($user)
    {
        return Client::where('is_active', true)->whereNotNull('meta_page_id')->whereNotNull('meta_ad_account_id')
            ->when($user->brandLimit(), fn ($q, $ids) => $q->whereIn('id', $ids))->orderBy('name')->get();
    }
}
