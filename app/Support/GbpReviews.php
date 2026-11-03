<?php

namespace App\Support;

use App\Models\Client;
use App\Models\GbpLocation;
use App\Models\GbpReview;
use App\Models\ReviewReplyLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Google reviews: sync (gbp:reviews, every 15 min) and the rules for new ones:
 * 4–5 stars → template auto reply after 10–30 min (client's auto reply on) · 3 stars → template draft for approval ·
 * 1–2 stars → no reply, Admin / Ops Manager / brand SMMs are alerted (in-app, email, push).
 * The first sync of a location only imports its old reviews (no replies, no alerts).
 */
class GbpReviews
{
    private const STARS = ['ONE' => 1, 'TWO' => 2, 'THREE' => 3, 'FOUR' => 4, 'FIVE' => 5];

    /** @return int new reviews */
    public static function syncAll(): int
    {
        $token = GoogleBusiness::token();
        $new = 0;
        foreach (GbpLocation::whereNotNull('client_id')->with('client')->get() as $location) {
            try {
                $new += self::sync($location, $token);
            } catch (Throwable $e) {
                report($e);
                $location->update(['reviews_error' => mb_substr($e->getMessage(), 0, 1000)]); // shown on Admin > Google Business
            }
        }

        return $new;
    }

    public static function sync(GbpLocation $location, string $token): int
    {
        $first = ! $location->reviews_synced_at;
        $new = 0;
        $pageToken = null;
        do {
            $page = GoogleBusiness::reviews($location, $token, $pageToken);
            foreach ($page['reviews'] as $data) {
                $new += self::store($location, $data, $first) ? 1 : 0;
            }
            $pageToken = $first ? $page['next'] : null; // later syncs: the newest 50 are enough every 15 minutes
        } while ($pageToken);
        $location->update(['reviews_synced_at' => now(), 'reviews_error' => null]);

        return $new;
    }

    /** Saves one review from the API; returns true when it is new */
    private static function store(GbpLocation $location, array $data, bool $importOnly): bool
    {
        $googleReply = $data['reviewReply'] ?? null;
        $fields = [
            'review_name' => $data['name'],
            'reviewer_name' => ($data['reviewer']['isAnonymous'] ?? false) ? null : ($data['reviewer']['displayName'] ?? null),
            'rating' => self::STARS[$data['starRating'] ?? ''] ?? 0,
            'comment' => $data['comment'] ?? null,
            'reviewed_at' => isset($data['createTime']) ? Carbon::parse($data['createTime']) : null,
        ];
        $review = GbpReview::firstWhere('review_id', $data['reviewId']);

        if ($review) {
            $review->update($fields);
            if ($googleReply && $review->status !== 'replied') { // answered straight on Google
                $review->update(['status' => 'replied', 'reply' => $googleReply['comment'] ?? '', 'replied_at' => Carbon::parse($googleReply['updateTime'] ?? now()), 'reply_due_at' => null]);
            }

            return false;
        }

        $review = GbpReview::create($fields + ['review_id' => $data['reviewId'], 'client_id' => $location->client_id, 'gbp_location_id' => $location->id]
            + ($googleReply ? ['status' => 'replied', 'reply' => $googleReply['comment'] ?? '', 'replied_at' => Carbon::parse($googleReply['updateTime'] ?? now())] : []));
        if (! $googleReply && ! $importOnly) {
            self::applyRules($review);
        }

        return true;
    }

    public static function applyRules(GbpReview $review): void
    {
        $review->setRelation('location', $review->location()->first());
        $client = $review->client;

        if ($review->rating <= 2) {
            Notify::send(Notify::brandManagers($client->id), $review->rating.'-star Google review',
                $client->name.': '.($review->reviewer_name ?? 'Someone').' gave '.$review->rating.' star(s). '.mb_strimwidth((string) $review->comment, 0, 200, '…'),
                route('reviews.index', ['client' => $client->id, 'status' => 'pending']));

            return;
        }

        $positive = $review->rating >= 4;
        if ($positive && ! $client->gbp_auto_reply) {
            return; // stays "To reply"
        }
        if (! $picked = ReviewTemplates::pick($review, $positive ? 'positive' : 'neutral')) {
            return;
        }
        $review->update(['draft' => $picked['text'], 'template_id' => $picked['template']->id, 'templated_at' => now()]
            + ($positive ? ['status' => 'scheduled', 'reply_due_at' => now()->addMinutes(random_int(10, 30))] : ['status' => 'draft']));
    }

    /**
     * Old reviews still without a reply: 4–5 stars get template replies spread out (one every 10–30 min, after any
     * already scheduled for this client), 3 stars get drafts for approval; 1–2 stars stay manual. Newest first, 100 per run.
     *
     * @return array{scheduled: int, drafts: int}
     */
    public static function backfill(Client $client): array
    {
        $done = ['scheduled' => 0, 'drafts' => 0];
        $last = GbpReview::where('client_id', $client->id)->where('status', 'scheduled')->max('reply_due_at');
        $due = $last && Carbon::parse($last)->isFuture() ? Carbon::parse($last) : now();
        $reviews = GbpReview::where('client_id', $client->id)->where('status', 'pending')->whereNull('draft')->where('rating', '>=', 3)
            ->latest('reviewed_at')->limit(100)->get();
        foreach ($reviews as $review) {
            $positive = $review->rating >= 4;
            $review->setRelation('client', $client)->setRelation('location', $review->location()->first());
            if (! $picked = ReviewTemplates::pick($review, $positive ? 'positive' : 'neutral')) {
                continue;
            }
            if ($positive) {
                $due = $due->copy()->addMinutes(random_int(10, 30));
            }
            // templated_at = due time, so rotation sees them in the order they go out
            $review->update(['draft' => $picked['text'], 'template_id' => $picked['template']->id, 'templated_at' => $positive ? $due : now()]
                + ($positive ? ['status' => 'scheduled', 'reply_due_at' => $due] : ['status' => 'draft']));
            $done[$positive ? 'scheduled' : 'drafts']++;
        }

        return $done;
    }

    /** Auto replies whose time has come (gbp:replies, every minute) */
    public static function sendDue(): int
    {
        $sent = 0;
        foreach (GbpReview::where('status', 'scheduled')->where('reply_due_at', '<=', now())->with('client')->limit(20)->get() as $review) {
            if (! $review->client->gbp_auto_reply) { // switched off meanwhile
                $review->update(['status' => 'pending', 'reply_due_at' => null]);

                continue;
            }
            try {
                self::reply($review, $review->draft, null, $review->template_id);
                $sent++;
            } catch (Throwable $e) {
                $attempts = $review->attempts + 1;
                $review->update(['attempts' => $attempts, 'error' => mb_substr($e->getMessage(), 0, 1000)]
                    + ($attempts >= 3 ? ['status' => 'pending', 'reply_due_at' => null] : ['reply_due_at' => now()->addMinutes(5)]));
            }
        }

        return $sent;
    }

    /** Send to Google, then mark replied and log it. $approver null = automatic. */
    public static function reply(GbpReview $review, string $text, ?User $approver, ?int $templateId = null): void
    {
        GoogleBusiness::replyToReview($review->review_name, $text);
        $review->update(['status' => 'replied', 'reply' => $text, 'replied_at' => now(), 'reply_due_at' => null, 'error' => null]);
        ReviewReplyLog::create(['gbp_review_id' => $review->id, 'template_id' => $templateId, 'final_text' => $text, 'sent_at' => now(), 'approved_by' => $approver?->id]);
    }
}
