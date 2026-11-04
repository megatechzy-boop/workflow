<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\Client;
use App\Models\GbpReview;
use App\Support\GbpReviews;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/** Google reviews of every client (synced by gbp:reviews); replies go out as the business */
class ReviewController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->hasPermission(Permission::PublishContent), 403);
        $filters = $request->validate([
            'client' => ['nullable', 'integer'],
            'stars' => ['nullable', 'integer', 'between:1,5'],
            'status' => ['nullable', Rule::in(array_merge(array_keys(GbpReview::STATUSES), ['open', 'all']))],
        ]);
        $status = $filters['status'] ?? 'open';

        return view('reviews.index', [
            'reviews' => GbpReview::with('client:id,name,is_healthcare', 'logs.approver:id,name')
                ->when($user->brandLimit(), fn ($q, $ids) => $q->whereIn('client_id', $ids))
                ->when($filters['client'] ?? null, fn ($q, $id) => $q->where('client_id', $id))
                ->when($filters['stars'] ?? null, fn ($q, $stars) => $q->where('rating', $stars))
                ->when($status === 'open', fn ($q) => $q->where('status', '!=', 'replied'))
                ->when(! in_array($status, ['open', 'all'], true), fn ($q) => $q->where('status', $status))
                ->latest('reviewed_at')->paginate(30)->withQueryString(),
            'clients' => Client::whereHas('gbpLocation')->when($user->brandLimit(), fn ($q, $ids) => $q->whereIn('id', $ids))->orderBy('name')->pluck('name', 'id'),
            'filters' => $filters + ['status' => $status],
            // old reviews the "Schedule replies" button would take (shown when one client is picked)
            'waiting' => ($filters['client'] ?? null) && $user->canSeeBrand((int) $filters['client'])
                ? GbpReview::where('client_id', $filters['client'])->where('status', 'pending')->where('rating', '>=', 3)->count() : 0,
        ]);
    }

    /** Old unanswered reviews of one client: replies scheduled one by one (4–5 stars), drafts for 3 stars */
    public function backfill(Request $request, Client $client): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermission(Permission::PublishContent) && $user->canSeeBrand($client->id), 403);
        if (! $client->gbp_auto_reply) {
            return back()->withErrors(['reply' => 'Turn on auto reply for '.$client->name.' first (Clients > Edit > Google reviews).']);
        }
        $done = GbpReviews::backfill($client);

        return back()->with('status', $done['scheduled'] + $done['drafts']
            ? $done['scheduled'].' reply(s) scheduled, one every 10–30 minutes; '.$done['drafts'].' draft(s) for 3-star reviews to approve.'
            : 'No old 3–5 star reviews waiting for a reply.');
    }

    /** Manual reply, or approve (and maybe edit) a template draft */
    public function reply(Request $request, GbpReview $review): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermission(Permission::PublishContent) && $user->canSeeBrand($review->client_id), 403);
        $text = $request->validate(['reply' => ['required', 'string', 'max:4000']])['reply'];

        try {
            GbpReviews::reply($review, $text, $user, $review->draft !== null ? $review->template_id : null);
        } catch (Throwable $e) {
            return back()->withErrors(['reply' => 'Google: '.$e->getMessage()]);
        }

        return back()->with('status', 'Reply posted on Google.');
    }
}
