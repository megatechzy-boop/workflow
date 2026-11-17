<?php

namespace App\Support;

use App\Enums\ContentStatus;
use App\Models\Content;
use App\Models\MetaPost;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Throwable;

/** Approved content → Facebook / Instagram of the client's connected Page (run by meta:publish every minute) */
class MetaPublisher
{
    /** Facebook / Instagram of this content's row, e.g. ['facebook', 'instagram'] */
    public static function platforms(Content $content): array
    {
        $keys = str_contains($content->post_type, '_')
            ? [$content->post_type] // legacy "instagram_post"
            : array_filter($content->task->item->post_types ?? [], fn ($k) => Content::typeOf($k) === $content->type());

        return array_values(array_intersect(['facebook', 'instagram'], array_map(fn ($k) => strstr($k, '_', true), $keys)));
    }

    /** Why it can't be published now, or null */
    public static function blocker(Content $content): ?string
    {
        $page = $content->task->item->calendar->client->metaPage;

        return match (true) {
            ! config('services.meta.app_id') => 'Meta app is not set up yet (META_APP_ID).',
            ! $page => 'Link this client to a Facebook Page first (Clients > Edit).',
            ! $content->hasCaption() => 'Add the caption first: a post without a caption cannot be scheduled or published.',
            ! self::platforms($content) => 'This post is not for Facebook or Instagram.',
            in_array('instagram', self::platforms($content), true) && ! $page->ig_user_id => 'The Page has no Instagram business account linked.',
            default => null,
        };
    }

    /** Instagram containers per post: an ERROR one (often Meta's own "Media upload has failed" 2207076) is made again */
    public const CONTAINER_TRIES = 3;

    /** Minutes between those tries */
    public const RETRY_MINUTES = 5;

    /** @return int how many platform posts were queued */
    /** $at: post at this time (meta:publish waits), null = right away */
    public static function queue(Content $content, User $by, ?\DateTimeInterface $at = null): int
    {
        $page = $content->task->item->calendar->client->metaPage;
        $made = 0;
        foreach (self::platforms($content) as $platform) {
            $post = MetaPost::firstOrNew(['content_id' => $content->id, 'platform' => $platform]);
            if ($post->exists && $post->status !== 'failed') {
                continue; // already queued or posted
            }
            $post->fill(['meta_page_id' => $page->id, 'status' => 'queued', 'publish_at' => $at, 'container_id' => null, 'error' => null, 'attempts' => 0, 'container_tries' => 0, 'queued_by' => $by->id])->save();
            $made++;
        }

        return $made;
    }

    public static function process(MetaPost $post): void
    {
        $content = $post->content;
        $page = $post->page;
        $format = $content->type()->value;
        $version = $content->versions()->where('number', $content->current_version)->with('media')->firstOrFail();
        $media = $version->media->sortBy('sort_order');
        $url = fn ($m) => URL::temporarySignedRoute('meta.media', now()->addHours(3), ['media' => $m->id]);
        $urls = $media->where('kind', $format === 'reel' ? 'video' : 'image')->map($url)->values()->all();
        $cover = $media->firstWhere('kind', 'cover');
        $caption = trim(($version->caption ?? '')."\n\n".($version->hashtags ?? ''));

        // Instagram media is taken from Facebook's copy once the Facebook post is out: Meta reads its own CDN
        // reliably, while our server is sometimes unreachable for Meta (DNS / firewall on the host); for reels
        // Facebook's MP4 is also re-encoded, so phone videos Instagram rejects (e.g. HEVC .mov) go through
        $facebook = $post->platform === 'instagram'
            ? MetaPost::where('content_id', $content->id)->where('platform', 'facebook')->first() : null;
        if ($facebook && ! $post->container_id && in_array($facebook->status, ['queued', 'processing'], true)) {
            return; // wait for the Facebook post (next run)
        }

        try {
            $post->increment('attempts');
            if ($post->platform === 'facebook') {
                $post->update(['status' => 'done', 'post_id' => MetaGraph::postToFacebook($page, $format, $urls, $caption)]);
            } else {
                // Reels: tries 1-2 upload the video bytes (try 2 without the cover, which Instagram fetches from our server),
                // try 3 gives Instagram Facebook's copy of the video as a link
                $reelTry = $format === 'reel' ? $post->container_tries : 0;
                $upload = $format === 'reel' && $reelTry < 2 && ($video = $media->firstWhere('kind', 'video'));
                if (! $post->container_id && ! $upload && $facebook?->status === 'done') {
                    try {
                        if ($format === 'reel') {
                            $source = MetaGraph::facebookVideoUrl($page, $facebook->post_id);
                            if (! $source && $post->attempts < 15) {
                                return; // Facebook is still processing the video: next run (then our own link)
                            }
                            $urls = $source ? [$source] : $urls;
                        } else {
                            $copies = MetaGraph::facebookPhotoUrls($page, $facebook->post_id);
                            $urls = count($copies) === count($urls) ? $copies : $urls;
                        }
                    } catch (Throwable $e) {
                        report($e); // fall back to our own links
                    }
                }
                if (! $post->container_id) {
                    $coverUrl = $cover && $reelTry === 0 ? $url($cover) : null;
                    $post->update(['status' => 'processing', 'container_tries' => $post->container_tries + 1, 'container_id' => $upload
                        ? MetaGraph::uploadInstagramReel($page, Storage::disk('local')->path($video->path), $caption, $coverUrl)
                        : MetaGraph::createInstagramContainer($page, $format, $urls, $caption, $coverUrl)]);
                }
                $status = MetaGraph::containerStatus($page, $post->container_id);
                if ($status === 'FINISHED') {
                    $post->update(['status' => 'done', 'post_id' => MetaGraph::publishInstagram($page, $post->container_id)]);
                } elseif (in_array($status, ['ERROR', 'EXPIRED'], true) || $post->attempts >= 30) {
                    $why = $status === 'ERROR' ? MetaGraph::containerError($page, $post->container_id) : '';
                    $error = "Instagram could not process the media ($status).".($why ? " $why" : '');
                    if ($status === 'ERROR' && $post->container_tries < self::CONTAINER_TRIES) {
                        // a failed container never recovers: make a new one in a few minutes
                        $at = now()->addMinutes(self::RETRY_MINUTES);
                        $post->update(['status' => 'queued', 'container_id' => null, 'attempts' => 0, 'publish_at' => $at,
                            'error' => mb_substr("Try {$post->container_tries} of ".self::CONTAINER_TRIES." failed, trying again at {$at->format('h:i A')}. $error", 0, 1000)]);
                    } else {
                        $post->update(['status' => 'failed', 'error' => mb_substr($error, 0, 1000)]);
                    }
                }
            }
        } catch (Throwable $e) {
            $post->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000)]);
        }

        // Every platform posted → the content is Published
        $all = MetaPost::where('content_id', $content->id)->pluck('status');
        if ($all->isNotEmpty() && $all->every(fn ($s) => $s === 'done') && $content->status !== ContentStatus::Published) {
            $content->update(['status' => ContentStatus::Published]);
        }
    }
}
