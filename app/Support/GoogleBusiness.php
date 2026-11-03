<?php

namespace App\Support;

use App\Models\GoogleAccount;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Small Google Business Profile client: OAuth login (offline, so we get a refresh token),
 * access-token renewal, and the list of locations the login manages.
 */
class GoogleBusiness
{
    public const SCOPE = 'https://www.googleapis.com/auth/business.manage';

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
                $locations[] = [
                    'account_name' => $account['name'], 'location_name' => $l['name'], 'title' => $l['title'] ?? $l['name'],
                    'city' => $l['storefrontAddress']['locality'] ?? null, 'phone' => $l['phoneNumbers']['primaryPhone'] ?? null,
                ];
            }
        }

        return $locations;
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
