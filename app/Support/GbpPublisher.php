<?php

namespace App\Support;

use App\Models\GbpPost;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Throwable;

/**
 * GBP posts → Google (gbp:posts, every minute). Up to 3 tries, 5 minutes apart; then Failed and the team is alerted.
 * Try 1 gives Google our signed image link; later tries upload the bytes instead (the host firewall blocks some crawlers),
 * which also adds the image to the location's Photos. GBP_POST_MEDIA=upload always uploads.
 */
class GbpPublisher
{
    public const MAX_ATTEMPTS = 3;

    public static function publishDue(): int
    {
        $posts = GbpPost::where('status', 'scheduled')->where('scheduled_at', '<=', now())
            ->where(fn ($q) => $q->whereNull('retry_at')->orWhere('retry_at', '<=', now()))
            ->with('location', 'client')->orderBy('scheduled_at')->limit(10)->get();
        foreach ($posts as $post) {
            // claim it first, so an overlapping run cannot post it twice
            if (GbpPost::whereKey($post->id)->where('status', 'scheduled')->update(['status' => 'publishing'])) {
                $post->status = 'publishing';
                $post->syncOriginalAttribute('status'); // so "back to scheduled" after a failed try is saved
                self::publish($post);
            }
        }

        return $posts->count();
    }

    public static function publish(GbpPost $post): void
    {
        $attempt = $post->attempts + 1;
        try {
            $upload = $attempt > 1 || config('services.google.post_media') === 'upload';
            $created = GoogleBusiness::createLocalPost($post->location, self::body($post, $upload ? self::uploadedUrl($post) : self::signedUrl($post)));
            if (($created['state'] ?? null) === 'REJECTED') {
                throw new RuntimeException('Google rejected the post (check the caption and image against Google\'s post rules).');
            }
            $post->update(['status' => 'published', 'attempts' => $attempt, 'published_at' => now(), 'retry_at' => null, 'error' => null,
                'google_post_name' => $created['name'] ?? null, 'search_url' => $created['searchUrl'] ?? null]);
        } catch (Throwable $e) {
            $error = mb_substr($e->getMessage(), 0, 1000);
            if ($attempt < self::MAX_ATTEMPTS) {
                $post->update(['status' => 'scheduled', 'attempts' => $attempt, 'retry_at' => now()->addMinutes(5), 'error' => $error]);

                return;
            }
            $post->update(['status' => 'failed', 'attempts' => $attempt, 'retry_at' => null, 'error' => $error]);
            Notify::send(Notify::brandManagers($post->client_id)->push($post->creator), 'GBP post failed',
                $post->client->name.': the Google post could not be published after '.$attempt.' tries. Google said: '.$error,
                route('gbp-posts.edit', $post));
        }
    }

    /** localPosts body (v4) */
    public static function body(GbpPost $post, string $imageUrl): array
    {
        $body = [
            'languageCode' => 'en',
            'summary' => $post->caption,
            'topicType' => ['update' => 'STANDARD', 'offer' => 'OFFER', 'event' => 'EVENT'][$post->post_type],
            'media' => [['mediaFormat' => 'PHOTO', 'sourceUrl' => $imageUrl]],
        ];
        if ($post->post_type !== 'update') {
            $body['event'] = ['title' => $post->title, 'schedule' => [
                'startDate' => self::date($post->starts_at), 'startTime' => self::time($post->starts_at),
                'endDate' => self::date($post->ends_at), 'endTime' => self::time($post->ends_at),
            ]];
        }
        if ($post->post_type === 'offer') { // offers have no button; the link becomes "Redeem online"
            $body['offer'] = array_filter(['couponCode' => $post->coupon, 'termsConditions' => $post->terms, 'redeemOnlineUrl' => $post->cta_url]);
        } elseif ($post->cta_type) {
            $body['callToAction'] = array_filter(['actionType' => $post->cta_type, 'url' => $post->cta_type === 'CALL' ? null : $post->cta_url]);
        }

        return $body;
    }

    /** Public link to the image (no login), valid for a week */
    public static function signedUrl(GbpPost $post): string
    {
        return URL::temporarySignedRoute('gbp.media', now()->addDays(7), ['post' => $post->id]);
    }

    private static function uploadedUrl(GbpPost $post): string
    {
        $disk = Storage::disk('local');

        return GoogleBusiness::uploadPhoto($post->location, $disk->get($post->image_path), $disk->mimeType($post->image_path) ?: 'image/jpeg');
    }

    private static function date($at): array
    {
        return ['year' => (int) $at->format('Y'), 'month' => (int) $at->format('n'), 'day' => (int) $at->format('j')];
    }

    private static function time($at): array
    {
        return ['hours' => (int) $at->format('G'), 'minutes' => (int) $at->format('i')];
    }
}
