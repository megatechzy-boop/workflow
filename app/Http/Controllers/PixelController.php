<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\Client;
use App\Models\MetaConnection;
use App\Support\MetaGraph;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * Meta ads > Pixel: one pixel per client (new or existing on their ad account), the code for their website,
 * events seen in the last 7 days and custom conversions ("thank-you page = Lead").
 */
class PixelController extends Controller
{
    public const EVENTS = ['LEAD' => 'Lead', 'CONTACT' => 'Contact', 'COMPLETE_REGISTRATION' => 'Registration', 'PURCHASE' => 'Purchase', 'SCHEDULE' => 'Booking', 'OTHER' => 'Other'];

    public function index(Request $request): View
    {
        $clients = Client::readyForAds($this->authorizeUser($request))->load('metaAdAccount');
        $client = $clients->firstWhere('id', (int) $request->client) ?? $clients->first();
        $token = MetaConnection::token();
        $pixels = $stats = $conversions = [];
        $error = null;

        if ($client && $token) {
            try {
                $act = $client->metaAdAccount->account_id;
                $pixels = MetaGraph::get("/$act/adspixels", ['access_token' => $token, 'fields' => 'id,name,last_fired_time'])['data'] ?? [];
                if ($client->meta_pixel_id) {
                    $stats = Cache::remember("pixel-stats:{$client->meta_pixel_id}", now()->addHour(), fn () => collect(MetaGraph::get("/{$client->meta_pixel_id}/stats", [
                        'access_token' => $token, 'aggregation' => 'event', 'start_time' => now()->subDays(7)->timestamp,
                    ])['data'] ?? [])->flatMap(fn ($bucket) => $bucket['data'] ?? [])->groupBy('value')->map(fn ($rows) => $rows->sum('count'))->sortDesc()->all());
                    $conversions = collect(MetaGraph::get("/$act/customconversions", ['access_token' => $token, 'fields' => 'id,name,rule,custom_event_type,pixel'])['data'] ?? [])
                        ->filter(fn ($c) => ($c['pixel']['id'] ?? null) === $client->meta_pixel_id)->values()->all();
                }
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return view('ads.pixel', ['clients' => $clients, 'client' => $client, 'pixels' => $pixels, 'stats' => $stats,
            'conversions' => $conversions, 'connected' => (bool) $token, 'error' => $error,
            'code' => $client?->meta_pixel_id ? self::baseCode($client->meta_pixel_id) : null]);
    }

    /** Meta's standard base code (goes in the website's <head>) */
    public static function baseCode(string $pixelId): string
    {
        return <<<HTML
            <!-- Meta Pixel -->
            <script>
            !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
            n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
            n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
            t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
            document,'script','https://connect.facebook.net/en_US/fbevents.js');
            fbq('init', '$pixelId');
            fbq('track', 'PageView');
            </script>
            <!-- End Meta Pixel -->
            HTML;
    }

    /** Use an existing pixel of the ad account, or make a new one */
    public function store(Request $request, Client $client): RedirectResponse
    {
        $this->authorizeClient($request, $client);
        $data = $request->validate(['pixel_id' => ['nullable', 'regex:/^\d+$/'], 'name' => ['nullable', 'required_without:pixel_id', 'string', 'max:100']]);
        $token = MetaConnection::token() ?? abort(422, 'Reconnect Facebook first.');
        $act = $client->metaAdAccount->account_id;

        try {
            $id = $data['pixel_id'] ?? null;
            if ($id) {
                $owned = collect(MetaGraph::get("/$act/adspixels", ['access_token' => $token, 'fields' => 'id'])['data'] ?? [])->pluck('id');
                abort_unless($owned->contains($id), 422, 'That pixel is not on this client\'s ad account.');
            } else {
                $id = MetaGraph::post("/$act/adspixels", ['name' => $data['name'], 'access_token' => $token])['id'];
            }
        } catch (Throwable $e) {
            return back()->with('status', 'Meta said: '.$e->getMessage());
        }
        $client->update(['meta_pixel_id' => $id]);

        return redirect()->route('ads.pixel', ['client' => $client->id])->with('status', 'Pixel set. Put the code below on the client\'s website.');
    }

    /** Custom conversion: a visit to a page whose link contains … counts as Lead / Purchase / … */
    public function conversion(Request $request, Client $client): RedirectResponse
    {
        $this->authorizeClient($request, $client);
        abort_unless($client->meta_pixel_id, 422);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'url_contains' => ['required', 'string', 'max:200'],
            'event' => ['required', Rule::in(array_keys(self::EVENTS))],
        ]);
        $token = MetaConnection::token() ?? abort(422, 'Reconnect Facebook first.');

        try {
            MetaGraph::post("/{$client->metaAdAccount->account_id}/customconversions", ['access_token' => $token, 'name' => $data['name'],
                'pixel_id' => $client->meta_pixel_id, 'custom_event_type' => $data['event'],
                'rule' => json_encode(['url' => ['i_contains' => $data['url_contains']]])]);
        } catch (Throwable $e) {
            return back()->with('status', 'Meta said: '.$e->getMessage());
        }

        return back()->with('status', 'Conversion added. It counts from the next visit to that page.');
    }

    private function authorizeUser(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasPermission(Permission::PublishContent), 403);

        return $user;
    }

    private function authorizeClient(Request $request, Client $client): void
    {
        abort_unless(Client::readyForAds($this->authorizeUser($request))->contains('id', $client->id), 403);
    }
}
