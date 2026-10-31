<?php

namespace App\Support;

use App\Models\MetaPage;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Small Facebook Graph API client: login, list Pages, post to a Page and to its Instagram account.
 * Media is fetched by Meta from short-lived signed URLs (the files themselves stay private).
 */
class MetaGraph
{
    /** Pages + Instagram publishing; works in Standard access for people with a role on the Meta app */
    public const SCOPES = ['pages_show_list', 'pages_read_engagement', 'pages_manage_posts', 'business_management',
        'instagram_basic', 'instagram_content_publish', 'ads_management', 'ads_read',
        'pages_read_user_content', 'pages_manage_engagement', 'instagram_manage_comments', 'leads_retrieval', 'pages_manage_ads'];

    public static function loginUrl(string $state): string
    {
        // "Facebook Login for Business" apps take the permissions from a configuration (META_CONFIG_ID), not from scope
        $permissions = config('services.meta.config_id')
            ? ['config_id' => config('services.meta.config_id')]
            : ['scope' => implode(',', self::SCOPES)];

        return 'https://www.facebook.com/v21.0/dialog/oauth?'.http_build_query([
            'client_id' => config('services.meta.app_id'),
            'redirect_uri' => route('admin.meta.callback'),
            'state' => $state,
        ] + $permissions);
    }

    /** OAuth code → long-lived user token (page tokens made from it do not expire) */
    public static function userToken(string $code): string
    {
        $short = self::get('/oauth/access_token', [
            'client_id' => config('services.meta.app_id'), 'client_secret' => config('services.meta.app_secret'),
            'redirect_uri' => route('admin.meta.callback'), 'code' => $code,
        ])['access_token'];

        return self::get('/oauth/access_token', [
            'grant_type' => 'fb_exchange_token', 'client_id' => config('services.meta.app_id'),
            'client_secret' => config('services.meta.app_secret'), 'fb_exchange_token' => $short,
        ])['access_token'];
    }

    /** @return array<int, array{page_id: string, name: string, access_token: string, ig_user_id: ?string, ig_username: ?string}> */
    public static function pages(string $userToken): array
    {
        $data = self::get('/me/accounts', ['access_token' => $userToken, 'limit' => 100,
            'fields' => 'id,name,access_token,instagram_business_account{id,username}'])['data'] ?? [];

        return array_map(fn ($p) => [
            'page_id' => $p['id'], 'name' => $p['name'], 'access_token' => $p['access_token'],
            'ig_user_id' => $p['instagram_business_account']['id'] ?? null,
            'ig_username' => $p['instagram_business_account']['username'] ?? null,
        ], $data);
    }

    /** Facebook: photo, multi-photo (carousel) or video post. Returns the post id. */
    public static function postToFacebook(MetaPage $page, string $format, array $urls, string $caption): string
    {
        $token = ['access_token' => $page->access_token];

        if ($format === 'reel') {
            return self::post("/{$page->page_id}/videos", $token + ['file_url' => $urls[0], 'description' => $caption])['id'];
        }
        if (count($urls) === 1) {
            $r = self::post("/{$page->page_id}/photos", $token + ['url' => $urls[0], 'message' => $caption]);

            return $r['post_id'] ?? $r['id'];
        }
        $attached = [];
        foreach ($urls as $i => $url) {
            $photo = self::post("/{$page->page_id}/photos", $token + ['url' => $url, 'published' => 'false']);
            $attached["attached_media[$i]"] = json_encode(['media_fbid' => $photo['id']]);
        }

        return self::post("/{$page->page_id}/feed", $token + $attached + ['message' => $caption])['id'];
    }

    /** Instagram step 1: media container (image, carousel or reel). Returns the container id. */
    public static function createInstagramContainer(MetaPage $page, string $format, array $urls, string $caption, ?string $coverUrl = null): string
    {
        $ig = "/{$page->ig_user_id}/media";
        $token = ['access_token' => $page->access_token];

        if ($format === 'reel') {
            return self::post($ig, $token + array_filter(['media_type' => 'REELS', 'video_url' => $urls[0], 'caption' => $caption, 'cover_url' => $coverUrl]))['id'];
        }
        if (count($urls) === 1) {
            return self::post($ig, $token + ['image_url' => $urls[0], 'caption' => $caption])['id'];
        }
        $children = array_map(fn ($url) => self::post($ig, $token + ['image_url' => $url, 'is_carousel_item' => 'true'])['id'], $urls);

        return self::post($ig, $token + ['media_type' => 'CAROUSEL', 'children' => implode(',', $children), 'caption' => $caption])['id'];
    }

    /** FINISHED | IN_PROGRESS | ERROR | EXPIRED */
    public static function containerStatus(MetaPage $page, string $containerId): string
    {
        return self::get("/$containerId", ['fields' => 'status_code', 'access_token' => $page->access_token])['status_code'] ?? 'IN_PROGRESS';
    }

    /** Instagram step 2: publish a finished container. Returns the media id. */
    public static function publishInstagram(MetaPage $page, string $containerId): string
    {
        return self::post("/{$page->ig_user_id}/media_publish", ['creation_id' => $containerId, 'access_token' => $page->access_token])['id'];
    }

    /** @return array<int, array{account_id: string, name: string, currency: ?string}> active ad accounts of the login */
    public static function adAccounts(string $userToken): array
    {
        $data = self::get('/me/adaccounts', ['access_token' => $userToken, 'limit' => 100, 'fields' => 'id,name,currency,account_status'])['data'] ?? [];

        return array_values(array_map(fn ($a) => ['account_id' => $a['id'], 'name' => $a['name'] ?? $a['id'], 'currency' => $a['currency'] ?? null],
            array_filter($data, fn ($a) => ($a['account_status'] ?? 1) === 1)));
    }

    /**
     * Boost a published Facebook post: engagement campaign + ad set (budget, dates, place, age) + ad using the post.
     * Amounts are in rupees; Meta wants the smallest unit (paise).
     *
     * @return array{campaign_id: string, adset_id: string, ad_id: string}
     */
    public static function boost(string $userToken, string $adAccount, string $postId, string $name, int $dailyBudget, int $days,
        ?string $city, int $radiusKm, int $ageMin, int $ageMax): array
    {
        $token = ['access_token' => $userToken];
        $geo = ['countries' => ['IN']];
        if ($city) {
            $key = self::get('/search', $token + ['type' => 'adgeolocation', 'location_types' => '["city"]', 'q' => $city, 'country_code' => 'IN'])['data'][0]['key'] ?? null;
            if (! $key) {
                throw new RuntimeException("City \"$city\" was not found.");
            }
            $geo = ['cities' => [['key' => $key, 'radius' => $radiusKm, 'distance_unit' => 'kilometer']]];
        }

        $campaign = self::post("/$adAccount/campaigns", $token + [
            'name' => "Boost · $name", 'objective' => 'OUTCOME_ENGAGEMENT', 'status' => 'ACTIVE', 'special_ad_categories' => '[]',
        ])['id'];
        $adset = self::post("/$adAccount/adsets", $token + [
            'name' => "Boost · $name", 'campaign_id' => $campaign, 'status' => 'ACTIVE',
            'daily_budget' => $dailyBudget * 100, 'billing_event' => 'IMPRESSIONS', 'optimization_goal' => 'POST_ENGAGEMENT',
            'destination_type' => 'ON_POST', 'bid_strategy' => 'LOWEST_COST_WITHOUT_CAP',
            'start_time' => now()->toIso8601String(), 'end_time' => now()->addDays($days)->toIso8601String(),
            'targeting' => json_encode(['geo_locations' => $geo, 'age_min' => $ageMin, 'age_max' => $ageMax, 'targeting_automation' => ['advantage_audience' => 0]]),
        ])['id'];
        $creative = self::post("/$adAccount/adcreatives", $token + ['name' => "Boost · $name", 'object_story_id' => $postId])['id'];
        $ad = self::post("/$adAccount/ads", $token + [
            'name' => "Boost · $name", 'adset_id' => $adset, 'creative' => json_encode(['creative_id' => $creative]), 'status' => 'ACTIVE',
        ])['id'];

        return ['campaign_id' => $campaign, 'adset_id' => $adset, 'ad_id' => $ad];
    }

    /**
     * Latest comments on a published post (newest 100).
     *
     * @return array<int, array{id: string, author: ?string, author_id: ?string, message: string, time: ?string}>
     */
    public static function comments(MetaPage $page, string $platform, string $postId): array
    {
        $token = ['access_token' => $page->access_token, 'limit' => 100];
        if ($platform === 'instagram') {
            $data = self::get("/$postId/comments", $token + ['fields' => 'id,username,text,timestamp'])['data'] ?? [];

            return array_map(fn ($c) => ['id' => $c['id'], 'author' => $c['username'] ?? null, 'author_id' => $c['username'] ?? null,
                'message' => $c['text'] ?? '', 'time' => $c['timestamp'] ?? null], $data);
        }
        $data = self::get("/$postId/comments", $token + ['fields' => 'id,from{id,name},message,created_time', 'order' => 'reverse_chronological'])['data'] ?? [];

        return array_map(fn ($c) => ['id' => $c['id'], 'author' => $c['from']['name'] ?? null, 'author_id' => $c['from']['id'] ?? null,
            'message' => $c['message'] ?? '', 'time' => $c['created_time'] ?? null], $data);
    }

    /** Reply as the Page / Instagram account. Returns the reply id. */
    public static function replyToComment(MetaPage $page, string $platform, string $commentId, string $message): string
    {
        $edge = $platform === 'instagram' ? 'replies' : 'comments';

        return self::post("/$commentId/$edge", ['message' => $message, 'access_token' => $page->access_token])['id'];
    }

    /** @return array<int, array{id: string, name: string}> lead forms of a Page */
    public static function leadForms(MetaPage $page): array
    {
        return self::get("/{$page->page_id}/leadgen_forms", ['access_token' => $page->access_token, 'fields' => 'id,name', 'limit' => 100])['data'] ?? [];
    }

    /** @return array<int, array{id: string, created_time: string, field_data: array}> leads of one form created after $since */
    public static function leads(MetaPage $page, string $formId, \DateTimeInterface $since): array
    {
        return self::get("/$formId/leads", ['access_token' => $page->access_token, 'fields' => 'id,created_time,field_data', 'limit' => 100,
            'filtering' => json_encode([['field' => 'time_created', 'operator' => 'GREATER_THAN', 'value' => $since->getTimestamp()]]),
        ])['data'] ?? [];
    }

    public static function get(string $path, array $query): array
    {
        return self::check(Http::timeout(30)->get(config('services.meta.graph').$path, $query));
    }

    public static function post(string $path, array $data): array
    {
        return self::check(Http::asForm()->timeout(120)->post(config('services.meta.graph').$path, $data));
    }

    private static function check($response): array
    {
        if ($response->failed()) {
            throw new RuntimeException($response->json('error.message') ?? 'Meta API error '.$response->status());
        }

        return $response->json();
    }
}
