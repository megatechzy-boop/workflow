<?php

namespace App\Support;

use App\Enums\ContentStatus;
use App\Models\Content;
use App\Models\MetaPost;
use App\Models\User;
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
            ! self::platforms($content) => 'This post is not for Facebook or Instagram.',
            in_array('instagram', self::platforms($content), true) && ! $page->ig_user_id => 'The Page has no Instagram business account linked.',
            default => null,
        };
    }

    /** @return int how many platform posts were queued */
    public static function queue(Content $content, User $by): int
    {
        $page = $content->task->item->calendar->client->metaPage;
        $made = 0;
        foreach (self::platforms($content) as $platform) {
            $post = MetaPost::firstOrNew(['content_id' => $content->id, 'platform' => $platform]);
            if ($post->exists && $post->status !== 'failed') {
                continue; // already queued or posted
            }
            $post->fill(['meta_page_id' => $page->id, 'status' => 'queued', 'container_id' => null, 'error' => null, 'attempts' => 0, 'queued_by' => $by->id])->save();
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

        try {
            $post->increment('attempts');
            if ($post->platform === 'facebook') {
                $post->update(['status' => 'done', 'post_id' => MetaGraph::postToFacebook($page, $format, $urls, $caption)]);
            } else {
                if (! $post->container_id) {
                    $post->update(['status' => 'processing', 'container_id' => MetaGraph::createInstagramContainer($page, $format, $urls, $caption, $cover ? $url($cover) : null)]);
                }
                $status = MetaGraph::containerStatus($page, $post->container_id);
                if ($status === 'FINISHED') {
                    $post->update(['status' => 'done', 'post_id' => MetaGraph::publishInstagram($page, $post->container_id)]);
                } elseif (in_array($status, ['ERROR', 'EXPIRED'], true) || $post->attempts >= 30) {
                    $post->update(['status' => 'failed', 'error' => "Instagram could not process the media ($status)."]);
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
