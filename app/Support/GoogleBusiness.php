<?php

namespace App\Support;

use App\Models\GbpLocation;
use App\Models\GbpReview;
use App\Models\GoogleAccount;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Small Google Business Profile client: OAuth login (offline, so we get a refresh token),
 * access-token renewal, the locations the login manages, and reviews.
 */
class GoogleBusiness
{
    public const SCOPE = 'https://www.googleapis.com/auth/business.manage';

    /** Reviews and local posts still live on the old v4 API */
    public const V4 = 'https://mybusiness.googleapis.com/v4';

    public static function loginUrl(string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => route('google.callback'),
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent', // always hands back a refresh token, also on reconnect
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);
    }

    /** @return array{access_token: string, refresh_token: string, expires_at: \Illuminate\Support\Carbon} */
    public static function exchangeCode(string $code): array
    {
        $r = self::tokenRequest(['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => route('google.callback')]);
        if (empty($r['refresh_token'])) {
            throw new RuntimeException('Google gave no refresh token. Remove the app at myaccount.google.com/permissions and connect again.');
        }

        return ['access_token' => $r['access_token'], 'refresh_token' => $r['refresh_token'], 'expires_at' => now()->addSeconds(($r['expires_in'] ?? 3600) - 60)];
    }

    /** A working access token, renewed with the refresh token when it has (almost) expired */
    public static function token(): string
    {
        $account = GoogleAccount::current() ?? throw new RuntimeException('Google is not connected (Admin > Google Business).');
        if ($account->expires_at && $account->expires_at->isFuture()) {
            return $account->access_token;
        }
        $r = self::tokenRequest(['grant_type' => 'refresh_token', 'refresh_token' => $account->refresh_token]);
        $account->update(['access_token' => $r['access_token'], 'expires_at' => now()->addSeconds(($r['expires_in'] ?? 3600) - 60)]);

        return $r['access_token'];
    }

    /** @return array<int, array{account_name: string, location_name: string, title: string, city: ?string, phone: ?string}> every location of every account */
    public static function locations(string $token): array
    {
        $locations = [];
        foreach (self::paged('https://mybusinessaccountmanagement.googleapis.com/v1/accounts', [], 'accounts', $token) as $account) {
            $rows = self::paged("https://mybusinessbusinessinformation.googleapis.com/v1/{$account['name']}/locations",
                ['readMask' => 'name,title,storefrontAddress,phoneNumbers'], 'locations', $token);
            foreach ($rows as $l) {
                // a location shown under several accounts comes in once (the first account; replies find the right one)
                $locations[$l['name']] ??= [
                    'account_name' => $account['name'], 'location_name' => $l['name'], 'title' => $l['title'] ?? $l['name'],
                    'city' => $l['storefrontAddress']['locality'] ?? null, 'phone' => $l['phoneNumbers']['primaryPhone'] ?? null,
                ];
            }
        }

        return array_values($locations);
    }

    /** One page of reviews, newest first (v4 API). @return array{reviews: array, next: ?string} */
    public static function reviews(GbpLocation $location, string $token, ?string $pageToken = null): array
    {
        $r = self::get(self::V4.'/'.$location->v4Path().'/reviews', ['pageSize' => 50, 'orderBy' => 'updateTime desc'] + array_filter(['pageToken' => $pageToken]), $token);

        return ['reviews' => $r['reviews'] ?? [], 'next' => $r['nextPageToken'] ?? null];
    }

    /** Reply (or replace our reply) as the business */
    public static function replyToReview(GbpReview $review, string $text): void
    {
        $token = self::token();
        $location = $review->location;
        $reviewId = Str::after($review->review_name, '/reviews/');
        $response = self::onLocation($location, $token, fn (string $account) => Http::withToken($token)->timeout(30)
            ->put(self::V4."/$account/{$location->location_name}/reviews/$reviewId/reply", ['comment' => $text]));
        if ($response->status() === 404) {
            throw new RuntimeException('Google could not find this review under any connected account (Requested entity was not found). The reviewer may have removed it.');
        }
        self::check($response);
    }

    /** Create a local post. @return array{name: string, searchUrl: ?string, state: ?string} */
    public static function createLocalPost(GbpLocation $location, array $body): array
    {
        $token = self::token();

        return self::check(self::onLocation($location, $token, fn (string $account) => Http::withToken($token)->timeout(60)
            ->post(self::V4."/$account/{$location->location_name}/localPosts", $body)));
    }

    /**
     * A location the login sees under several accounts (personal + location group / organisation) can be read
     * through any of them, but Google accepts replies and posts only through the account that manages it, else 404
     * "Requested entity was not found". On a 404 the other accounts are tried; the one that works is remembered.
     */
    private static function onLocation(GbpLocation $location, string $token, callable $request): Response
    {
        $response = $request($location->account_name);
        if ($response->status() !== 404) {
            return $response;
        }
        foreach (self::paged('https://mybusinessaccountmanagement.googleapis.com/v1/accounts', [], 'accounts', $token) as $account) {
            if ($account['name'] === $location->account_name) {
                continue;
            }
            $try = $request($account['name']);
            if ($try->successful()) {
                self::moveLocation($location, $account['name']);

                return $try;
            }
        }

        return $response;
    }

    private static function moveLocation(GbpLocation $location, string $account): void
    {
        $old = $location->account_name.'/';
        $location->update(['account_name' => $account]);
        GbpReview::where('gbp_location_id', $location->id)->where('review_name', 'like', $old.'%')->get()
            ->each(fn (GbpReview $r) => $r->update(['review_name' => $account.'/'.Str::after($r->review_name, $old)]));
    }

    /**
     * Upload image bytes as a location photo and return its public googleusercontent URL.
     * Used when Google cannot fetch the image from our own link (the host firewall blocks some crawlers).
     */
    public static function uploadPhoto(GbpLocation $location, string $bytes, string $mime): string
    {
        $token = self::token();
        $parent = self::V4.'/'.$location->v4Path().'/media';
        $ref = self::check(Http::withToken($token)->timeout(30)->post($parent.':startUpload'))['resourceName'];
        self::check(Http::withToken($token)->timeout(120)->withBody($bytes, $mime)
            ->post('https://mybusiness.googleapis.com/upload/v1/media/'.$ref.'?upload_type=media'));
        $item = self::check(Http::withToken($token)->timeout(60)->post($parent, [
            'mediaFormat' => 'PHOTO', 'locationAssociation' => ['category' => 'ADDITIONAL'], 'dataRef' => ['resourceName' => $ref],
        ]));

        return $item['googleUrl'] ?? throw new RuntimeException('Google did not return a link for the uploaded photo.');
    }

    public static function get(string $url, array $query, string $token): array
    {
        return self::check(Http::withToken($token)->timeout(30)->get($url, $query));
    }

    /** Follows nextPageToken; returns the items under $key from all pages */
    private static function paged(string $url, array $query, string $key, string $token): array
    {
        $items = [];
        $pageToken = null;
        do {
            $r = self::get($url, $query + ['pageSize' => 100] + array_filter(['pageToken' => $pageToken]), $token);
            array_push($items, ...($r[$key] ?? []));
            $pageToken = $r['nextPageToken'] ?? null;
        } while ($pageToken);

        return $items;
    }

    private static function tokenRequest(array $data): array
    {
        $response = Http::asForm()->timeout(30)->post('https://oauth2.googleapis.com/token', $data + [
            'client_id' => config('services.google.client_id'), 'client_secret' => config('services.google.client_secret'),
        ]);
        if ($response->failed()) {
            throw new RuntimeException('Google login: '.($response->json('error_description') ?? $response->json('error') ?? 'error '.$response->status()));
        }

        return $response->json();
    }

    private static function check($response): array
    {
        if ($response->failed()) {
            throw new RuntimeException($response->json('error.message') ?? 'Google API error '.$response->status());
        }

        return $response->json() ?? [];
    }
}
