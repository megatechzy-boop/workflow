<?php

namespace App\Support;

use App\Enums\Role;
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
        $location->update(['reviews_synced_at' => now()]);

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
            Notify::send(self::managers($client->id), $review->rating.'-star Google review',
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

    /** Admin + Ops Managers + the brand's Social Media Managers */
    private static function managers(int $clientId)
    {
        return Notify::role(Role::Admin, Role::Manager)
            ->merge(Notify::role(Role::SocialMediaManager)->filter(fn (User $u) => $u->brandLimit() && $u->canSeeBrand($clientId)));
    }
}
