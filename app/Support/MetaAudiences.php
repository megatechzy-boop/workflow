<?php

namespace App\Support;

use App\Models\MetaAudience;
use App\Models\MetaConnection;
use RuntimeException;

/**
 * Custom audiences on the client's ad account: Page / Instagram engagers and website visitors (rules),
 * a customer list (phones / emails, SHA-256 hashed here; Meta never gets them in plain text) and lookalikes.
 */
class MetaAudiences
{
    /** @param  array<int, string>  $contacts  phones or emails, one each (list type only) */
    public static function create(MetaAudience $audience, array $contacts = []): void
    {
        $token = MetaConnection::token() ?? throw new RuntimeException('Reconnect Facebook with ads access first.');
        $act = $audience->adAccount->account_id;
        $t = ['access_token' => $token];
        $client = $audience->client;
        $retention = ($audience->days ?? 30) * 86400;
        $rule = fn (string $id, string $type, array $filter) => json_encode(['inclusions' => ['operator' => 'or', 'rules' => [[
            'event_sources' => [['id' => $id, 'type' => $type]], 'retention_seconds' => $retention,
            'filter' => ['operator' => 'and', 'filters' => [$filter]],
        ]]]]);

        $params = match ($audience->type) {
            'page' => ['rule' => $rule(($client->metaPage ?? throw new RuntimeException('Link this client to a Facebook Page first.'))->page_id,
                'page', ['field' => 'event', 'operator' => 'eq', 'value' => 'page_engaged'])],
            'instagram' => ['rule' => $rule($client->metaPage?->ig_user_id ?? throw new RuntimeException('The Page has no Instagram business account linked.'),
                'ig_business', ['field' => 'event', 'operator' => 'eq', 'value' => 'ig_business_profile_all'])],
            'website' => ['rule' => $rule($client->meta_pixel_id ?? throw new RuntimeException('Set up this client\'s pixel first (Meta ads > Pixel).'),
                'pixel', ['field' => 'url', 'operator' => 'i_contains', 'value' => ''])],
            'list' => ['subtype' => 'CUSTOM', 'customer_file_source' => 'USER_PROVIDED_ONLY'],
            'lookalike' => ['subtype' => 'LOOKALIKE', 'origin_audience_id' => $audience->source?->audience_id ?? throw new RuntimeException('Pick a ready audience to copy.'),
                'lookalike_spec' => json_encode(['country' => 'IN', 'ratio' => $audience->lookalike_percent / 100, 'type' => 'similarity'])],
        };

        $id = MetaGraph::post("/$act/customaudiences", $t + ['name' => $audience->name] + $params)['id'];
        $audience->update(['audience_id' => $id, 'status' => 'ready', 'error' => null]);

        if ($audience->type === 'list') {
            $rows = self::hashed($contacts);
            foreach (array_chunk($rows, 5000) as $chunk) {
                MetaGraph::post("/$id/users", $t + ['payload' => json_encode(['schema' => ['EMAIL', 'PHONE'], 'data' => $chunk])]);
            }
            $audience->update(['list_count' => count($rows)]);
        }
    }

    /** "+91 98765-43210" → sha256("919876543210"), "A@B.com " → sha256("a@b.com"); one row [email, phone] per contact */
    public static function hashed(array $contacts): array
    {
        return collect($contacts)->map(fn ($c) => trim((string) $c))->filter()->unique()->map(function ($c) {
            if (str_contains($c, '@')) {
                return [hash('sha256', mb_strtolower($c)), ''];
            }
            $digits = ltrim(preg_replace('/\D/', '', $c), '0');
            $digits = strlen($digits) === 10 ? '91'.$digits : $digits;

            return strlen($digits) >= 11 ? ['', hash('sha256', $digits)] : null;
        })->filter()->unique(fn ($row) => implode('|', $row))->values()->all();
    }
}
