<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\Client;
use App\Models\MetaComment;
use App\Models\MetaLead;
use App\Models\MetaPage;
use App\Models\MetaPost;
use App\Models\User;
use Illuminate\Support\Carbon;
use Throwable;

/** meta:sync — pulls new comments on our posts and new lead-form leads, and tells the right people */
class MetaInbox
{
    /** Posts older than this are not checked for comments any more */
    public const COMMENT_DAYS = 30;

    public static function syncComments(): int
    {
        $new = 0;
        $posts = MetaPost::where('status', 'done')->whereNotNull('post_id')->where('updated_at', '>=', now()->subDays(self::COMMENT_DAYS))
            ->with('page', 'content.task.item.calendar.client')->get();

        foreach ($posts as $post) {
            try {
                $comments = MetaGraph::comments($post->page, $post->platform, $post->post_id);
            } catch (Throwable $e) {
                report($e);

                continue;
            }
            $clientId = $post->content?->task?->item?->calendar?->client_id;
            $added = 0;
            foreach ($comments as $c) {
                // skip our own replies (the Page / the Instagram account)
                if (in_array($c['author_id'], [$post->page->page_id, $post->page->ig_username], true)) {
                    continue;
                }
                $comment = MetaComment::firstOrCreate(['comment_id' => $c['id']], [
                    'meta_post_id' => $post->id, 'client_id' => $clientId, 'platform' => $post->platform,
                    'author' => $c['author'], 'message' => $c['message'], 'commented_at' => $c['time'] ? Carbon::parse($c['time']) : null,
                ]);
                $added += $comment->wasRecentlyCreated ? 1 : 0;
            }
            if ($added) {
                $new += $added;
                Notify::send(self::team($clientId), 'New comments', "$added new comment(s) on a ".($post->content?->task?->item?->calendar?->client?->name ?? $post->page->name).' '.ucfirst($post->platform).' post', route('meta.comments'));
            }
        }

        return $new;
    }

    public static function syncLeads(): int
    {
        $new = 0;
        foreach (MetaPage::all() as $page) {
            $client = Client::where('meta_page_id', $page->id)->first();
            $since = MetaLead::where('meta_page_id', $page->id)->max('lead_at');
            $since = $since ? Carbon::parse($since) : now()->subDays(30);
            try {
                $forms = MetaGraph::leadForms($page);
                $added = 0;
                foreach ($forms as $form) {
                    foreach (MetaGraph::leads($page, $form['id'], $since) as $lead) {
                        $fields = collect($lead['field_data'] ?? [])->mapWithKeys(fn ($f) => [$f['name'] => implode(', ', $f['values'] ?? [])])->all();
                        $row = MetaLead::firstOrCreate(['lead_id' => $lead['id']], [
                            'meta_page_id' => $page->id, 'client_id' => $client?->id, 'form_name' => $form['name'] ?? null,
                            'name' => $fields['full_name'] ?? trim(($fields['first_name'] ?? '').' '.($fields['last_name'] ?? '')) ?: null,
                            'phone' => $fields['phone_number'] ?? $fields['phone'] ?? null, 'email' => $fields['email'] ?? null,
                            'fields' => $fields, 'lead_at' => Carbon::parse($lead['created_time']),
                        ]);
                        $added += $row->wasRecentlyCreated ? 1 : 0;
                    }
                }
            } catch (Throwable $e) {
                report($e);

                continue;
            }
            if ($added) {
                $new += $added;
                $customers = $client ? User::where('role', Role::Client)->where('client_id', $client->id)->where('is_active', true)->get() : collect();
                Notify::send(self::team($client?->id)->merge($customers), 'New leads', "$added new lead(s) for ".($client->name ?? $page->name), route('meta.leads'));
            }
        }

        return $new;
    }

    /** Social Media Managers of the brand (or all of them when nobody has it ticked) */
    private static function team(?int $clientId)
    {
        $smms = Notify::role(Role::SocialMediaManager);
        $forBrand = $smms->filter(fn (User $u) => $u->brandLimit() && $u->canSeeBrand($clientId));

        return $forBrand->isNotEmpty() ? $forBrand->values() : $smms->filter(fn (User $u) => ! $u->brandLimit())->values();
    }
}
