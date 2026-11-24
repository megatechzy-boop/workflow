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
                        if (MetaLead::where('lead_id', $lead['id'])->exists()) {
                            continue;
                        }
                        $phone = $fields['phone_number'] ?? $fields['phone'] ?? null;
                        $key = MetaLead::phoneKey($phone);
                        $row = MetaLead::create([
                            'lead_id' => $lead['id'], 'meta_page_id' => $page->id, 'client_id' => $client?->id, 'form_name' => $form['name'] ?? null,
                            'name' => $fields['full_name'] ?? trim(($fields['first_name'] ?? '').' '.($fields['last_name'] ?? '')) ?: null,
                            'phone' => $phone, 'email' => $fields['email'] ?? null, 'phone_key' => $key,
                            'is_repeat' => $key && MetaLead::where('phone_key', $key)->where('client_id', $client?->id)->exists(),
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
                self::tellLeadPeople($client, 'New leads', "$added new lead(s) for ".($client->name ?? $page->name), route('meta.leads'));
            }
        }

        return $new;
    }

    /** Daily: one note per client whose leads have a follow-up today (or a missed one) */
    public static function remindFollowUps(): int
    {
        $due = MetaLead::whereNotNull('follow_up_on')->whereDate('follow_up_on', '<=', today())
            ->whereNotIn('status', ['won', 'lost'])->get()->groupBy('client_id');
        foreach ($due as $clientId => $leads) {
            $client = $clientId ? Client::find($clientId) : null;
            $missed = $leads->filter(fn ($l) => $l->follow_up_on->lt(today()))->count();
            self::tellLeadPeople($client, 'Lead follow-ups today',
                $leads->count().' lead follow-up(s) for '.($client->name ?? 'leads').($missed ? " ($missed missed)" : ''),
                route('meta.leads', ['followup' => 'today'] + ($clientId ? ['client' => $clientId] : [])));
        }

        return $due->flatten()->count();
    }

    /**
     * Brand SMMs + the client's own Customer logins. The team gets an email only for Mega Techzy's own
     * (internal brand) leads; client brands' leads reach the team by bell + push. Customers always get the email.
     */
    private static function tellLeadPeople(?Client $client, string $title, string $message, string $url): void
    {
        $customers = $client ? User::where('role', Role::Client)->where('client_id', $client->id)->where('is_active', true)->get() : collect();

        Notify::send($customers, $title, $message, $url);
        Notify::send(self::team($client?->id), $title, $message, $url, (bool) $client?->is_internal);
    }

    /** Social Media Managers of the brand (or all of them when nobody has it ticked) */
    private static function team(?int $clientId)
    {
        $smms = Notify::role(Role::SocialMediaManager);
        $forBrand = $smms->filter(fn (User $u) => $u->brandLimit() && $u->canSeeBrand($clientId));

        return $forBrand->isNotEmpty() ? $forBrand->values() : $smms->filter(fn (User $u) => ! $u->brandLimit())->values();
    }
}
