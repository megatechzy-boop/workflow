<?php

namespace App\Support;

use App\Models\MetaPage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Numbers for the reports, read live from Meta and cached for 3 hours.
 * Anything Meta refuses (missing permission, deleted post) comes back as null and shows as "—".
 */
class MetaInsights
{
    /** What counts as a "result" for each ad goal (first action type Meta reports wins) */
    public const RESULTS = [
        'whatsapp' => ['onsite_conversion.messaging_conversation_started_7d'],
        'messages' => ['onsite_conversion.messaging_conversation_started_7d'],
        'calls' => ['click_to_call_native_call_placed', 'click_to_call_call_confirm'],
        'website' => ['link_click'],
        'awareness' => [],
        'boost' => ['post_engagement'],
    ];

    /** @return array{reach: ?int, likes: ?int, comments: ?int, shares: ?int} */
    public static function post(MetaPage $page, string $platform, string $postId): array
    {
        return Cache::remember("meta-post-stats:$platform:$postId", now()->addHours(3), function () use ($page, $platform, $postId) {
            $token = ['access_token' => $page->access_token];
            $stats = ['reach' => null, 'likes' => null, 'comments' => null, 'shares' => null];

            if ($platform === 'instagram') {
                $media = self::try(fn () => MetaGraph::get("/$postId", $token + ['fields' => 'like_count,comments_count']));
                $insights = self::try(fn () => MetaGraph::get("/$postId/insights", $token + ['metric' => 'reach,shares']));
                $metric = fn ($name) => collect($insights['data'] ?? [])->firstWhere('name', $name)['values'][0]['value'] ?? null;

                return ['reach' => $metric('reach'), 'likes' => $media['like_count'] ?? null, 'comments' => $media['comments_count'] ?? null, 'shares' => $metric('shares')];
            }

            $post = self::try(fn () => MetaGraph::get("/$postId", $token + ['fields' => 'reactions.summary(total_count).limit(0),comments.summary(total_count).limit(0),shares']));
            $insights = self::try(fn () => MetaGraph::get("/$postId/insights", $token + ['metric' => 'post_impressions_unique']));
            if ($post) {
                $stats = ['reach' => null, 'likes' => $post['reactions']['summary']['total_count'] ?? 0,
                    'comments' => $post['comments']['summary']['total_count'] ?? 0, 'shares' => $post['shares']['count'] ?? 0];
            }
            $stats['reach'] = $insights['data'][0]['values'][0]['value'] ?? null;

            return $stats;
        });
    }

    /** @return array{spend: ?float, reach: ?int, impressions: ?int, clicks: ?int, results: ?int} for one ad within the dates */
    public static function ad(string $userToken, string $adId, string $goal, Carbon $from, Carbon $to): array
    {
        return Cache::remember("meta-ad-stats:$adId:{$from->toDateString()}:{$to->toDateString()}", now()->addHours(3), function () use ($userToken, $adId, $goal, $from, $to) {
            $row = self::try(fn () => MetaGraph::get("/$adId/insights", ['access_token' => $userToken, 'fields' => 'spend,reach,impressions,clicks,actions',
                'time_range' => json_encode(['since' => $from->toDateString(), 'until' => $to->toDateString()])]));
            if ($row === null) {
                return ['spend' => null, 'reach' => null, 'impressions' => null, 'clicks' => null, 'results' => null];
            }
            $row = $row['data'][0] ?? [];
            $actions = collect($row['actions'] ?? [])->pluck('value', 'action_type');
            $type = collect(self::RESULTS[$goal] ?? [])->first(fn ($t) => $actions->has($t));

            return [
                'spend' => (float) ($row['spend'] ?? 0), 'reach' => (int) ($row['reach'] ?? 0), 'impressions' => (int) ($row['impressions'] ?? 0),
                'clicks' => (int) ($row['clicks'] ?? 0), 'results' => $goal === 'awareness' ? (int) ($row['reach'] ?? 0) : (int) ($type ? $actions[$type] : 0),
            ];
        });
    }

    private static function try(callable $call): ?array
    {
        try {
            return $call();
        } catch (Throwable) {
            return null;
        }
    }
}
