<?php

namespace App\Support;

use App\Models\MetaAd;
use App\Models\MetaConnection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Ad Setup: one campaign + ad set + ad per goal, created PAUSED so someone checks it before money is spent.
 * Goal → objective / optimisation / button follows Meta's current "outcome" objectives.
 */
class MetaAds
{
    private const GOALS = [
        'whatsapp' => ['OUTCOME_ENGAGEMENT', 'CONVERSATIONS', 'WHATSAPP', 'WHATSAPP_MESSAGE'],
        'messages' => ['OUTCOME_ENGAGEMENT', 'CONVERSATIONS', 'MESSENGER', 'MESSAGE_PAGE'],
        'calls' => ['OUTCOME_TRAFFIC', 'QUALITY_CALL', 'PHONE_CALL', 'CALL_NOW'],
        'website' => ['OUTCOME_TRAFFIC', 'LINK_CLICKS', 'WEBSITE', 'LEARN_MORE'],
        'awareness' => ['OUTCOME_AWARENESS', 'REACH', null, 'LEARN_MORE'],
    ];

    /** Creates everything paused and stores the ids on the ad */
    public static function create(MetaAd $ad): void
    {
        $token = MetaConnection::token() ?? throw new RuntimeException('Reconnect Facebook with ads access first.');
        $page = $ad->client->metaPage ?? throw new RuntimeException('Link this client to a Facebook Page first.');
        $act = $ad->adAccount->account_id;
        [$objective, $optimization, $destination, $button] = self::GOALS[$ad->goal];
        $t = ['access_token' => $token];

        $imageHash = self::uploadImage($token, $act, $ad->image_path);

        $campaign = MetaGraph::post("/$act/campaigns", $t + [
            'name' => $ad->name, 'objective' => $objective, 'status' => 'PAUSED', 'special_ad_categories' => '[]',
        ])['id'];

        $adset = MetaGraph::post("/$act/adsets", $t + array_filter([
            'name' => $ad->name, 'campaign_id' => $campaign, 'status' => 'PAUSED',
            'daily_budget' => $ad->daily_budget * 100, 'billing_event' => 'IMPRESSIONS', 'optimization_goal' => $optimization,
            'destination_type' => $destination, 'bid_strategy' => 'LOWEST_COST_WITHOUT_CAP',
            'promoted_object' => in_array($ad->goal, ['whatsapp', 'messages', 'calls'], true) ? json_encode(['page_id' => $page->page_id]) : null,
            'start_time' => $ad->start_on->copy()->startOfDay()->max(now())->toIso8601String(),
            'end_time' => $ad->end_on->copy()->endOfDay()->toIso8601String(),
            'targeting' => json_encode(self::targeting($token, $ad)),
        ]))['id'];

        $link = match ($ad->goal) {
            'website' => $ad->url,
            'calls' => 'tel:'.$ad->phone,
            'whatsapp' => 'https://api.whatsapp.com/send',
            default => $ad->url ?: 'https://www.facebook.com/'.$page->page_id,
        };
        $creative = MetaGraph::post("/$act/adcreatives", $t + ['name' => $ad->name, 'object_story_spec' => json_encode(array_filter([
            'page_id' => $page->page_id,
            'instagram_user_id' => $page->ig_user_id,
            'link_data' => array_filter([
                'image_hash' => $imageHash, 'message' => $ad->primary_text, 'name' => $ad->headline, 'link' => $link,
                'call_to_action' => ['type' => $button, 'value' => ['link' => $link]],
            ]),
        ]))])['id'];

        $adId = MetaGraph::post("/$act/ads", $t + [
            'name' => $ad->name, 'adset_id' => $adset, 'creative' => json_encode(['creative_id' => $creative]), 'status' => 'PAUSED',
        ])['id'];

        $ad->update(['campaign_id' => $campaign, 'adset_id' => $adset, 'ad_id' => $adId, 'status' => 'paused', 'error' => null]);
    }

    /** Start or pause all three levels */
    public static function setStatus(MetaAd $ad, string $status): void
    {
        $token = MetaConnection::token() ?? throw new RuntimeException('Reconnect Facebook with ads access first.');
        foreach ([$ad->campaign_id, $ad->adset_id, $ad->ad_id] as $id) {
            MetaGraph::post("/$id", ['status' => strtoupper($status), 'access_token' => $token]);
        }
        $ad->update(['status' => $status]);
    }

    private static function uploadImage(string $token, string $act, string $path): string
    {
        $data = MetaGraph::post("/$act/adimages", ['bytes' => base64_encode(Storage::disk('local')->get($path)), 'access_token' => $token]);

        return collect($data['images'] ?? [])->first()['hash'] ?? throw new RuntimeException('Meta did not accept the image.');
    }

    private static function targeting(string $token, MetaAd $ad): array
    {
        $geo = ['countries' => ['IN']];
        if ($ad->city) {
            $key = MetaGraph::get('/search', ['access_token' => $token, 'type' => 'adgeolocation', 'location_types' => '["city"]', 'q' => $ad->city, 'country_code' => 'IN'])['data'][0]['key'] ?? null;
            $geo = $key ? ['cities' => [['key' => $key, 'radius' => $ad->radius_km ?: 10, 'distance_unit' => 'kilometer']]]
                : throw new RuntimeException("City \"{$ad->city}\" was not found.");
        }
        $interests = collect(explode(',', (string) $ad->interests))->map(fn ($w) => trim($w))->filter()
            ->map(fn ($w) => MetaGraph::get('/search', ['access_token' => $token, 'type' => 'adinterest', 'q' => $w])['data'][0] ?? null)
            ->filter()->map(fn ($i) => ['id' => $i['id'], 'name' => $i['name']])->values()->all();

        return array_filter([
            'geo_locations' => $geo, 'age_min' => $ad->age_min, 'age_max' => $ad->age_max,
            'genders' => ['male' => [1], 'female' => [2]][$ad->gender] ?? null,
            'flexible_spec' => $interests ? [['interests' => $interests]] : null,
            'targeting_automation' => ['advantage_audience' => 0],
        ]);
    }
}
