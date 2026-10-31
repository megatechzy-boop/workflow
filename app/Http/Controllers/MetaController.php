<?php

namespace App\Http\Controllers;

use App\Enums\ContentStatus;
use App\Enums\Permission;
use App\Models\Content;
use App\Models\ContentMedia;
use App\Models\MetaAdAccount;
use App\Models\MetaBoost;
use App\Models\MetaConnection;
use App\Models\MetaPage;
use App\Models\MetaPost;
use App\Support\MetaGraph;
use App\Support\MetaPublisher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/** Admin connects Facebook once (all Pages + Instagram come in); approved content is queued for posting */
class MetaController extends Controller
{
    public function index(): View
    {
        return view('meta.index', ['pages' => MetaPage::orderBy('name')->get(), 'ready' => (bool) config('services.meta.app_id'), 'adAccounts' => MetaAdAccount::orderBy('name')->get()]);
    }

    public function connect(Request $request): RedirectResponse
    {
        abort_unless(config('services.meta.app_id'), 422, 'META_APP_ID is not set.');
        $state = Str::random(40);
        $request->session()->put('meta_state', $state);

        return redirect()->away(MetaGraph::loginUrl($state));
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! $request->filled('code') || $request->input('state') !== $request->session()->pull('meta_state')) {
            return redirect()->route('admin.meta.index')->with('status', 'Facebook connection was cancelled.');
        }

        try {
            $userToken = MetaGraph::userToken($request->input('code'));
            $pages = MetaGraph::pages($userToken);
            $adAccounts = rescue(fn () => MetaGraph::adAccounts($userToken), [], false); // ads permission may not be given
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('admin.meta.index')->with('status', 'Facebook said: '.$e->getMessage());
        }
        foreach ($pages as $page) {
            MetaPage::updateOrCreate(['page_id' => $page['page_id']], $page);
        }
        foreach ($adAccounts as $account) {
            MetaAdAccount::updateOrCreate(['account_id' => $account['account_id']], $account);
        }
        MetaConnection::query()->delete();
        MetaConnection::create(['user_token' => $userToken]);

        return redirect()->route('admin.meta.index')->with('status', count($pages).' Page(s) and '.count($adAccounts)
            .' ad account(s) connected. Now link each client to its Page and ad account (Clients > Edit).');
    }

    /** Boost a post that is live on Facebook: an ad on the client's ad account (the client pays Meta) */
    public function boost(Request $request, MetaPost $metaPost): RedirectResponse
    {
        $content = $metaPost->content;
        // Same people who publish (the post is already Published, so not the markStatus gate)
        abort_unless($request->user()->hasPermission(Permission::PublishContent) && $request->user()->can('view', $content), 403);
        $data = $request->validate([
            'daily_budget' => ['required', 'integer', 'min:100', 'max:100000'],
            'days' => ['required', 'integer', 'min:1', 'max:30'],
            'city' => ['nullable', 'string', 'max:80'],
            'radius_km' => ['nullable', 'integer', 'min:1', 'max:80'],
            'age_min' => ['required', 'integer', 'min:18', 'max:65'],
            'age_max' => ['required', 'integer', 'min:18', 'max:65', 'gte:age_min'],
        ]);
        $account = $content->task->item->calendar->client->metaAdAccount;
        $token = MetaConnection::token();
        abort_unless($metaPost->platform === 'facebook' && $metaPost->status === 'done' && str_contains((string) $metaPost->post_id, '_'), 422, 'Only a published Facebook post can be boosted.');
        if (! $account || ! $token) {
            return back()->with('status', 'Link this client to an ad account first (Clients > Edit), after reconnecting Facebook with ads access.');
        }

        $boost = MetaBoost::create($data + ['radius_km' => $data['radius_km'] ?? 10, 'meta_post_id' => $metaPost->id,
            'meta_ad_account_id' => $account->id, 'created_by' => $request->user()->id]);
        try {
            $ids = MetaGraph::boost($token, $account->account_id, $metaPost->post_id, $content->task->item->calendar->client->name.' #'.$content->id,
                $boost->daily_budget, $boost->days, $boost->city, $boost->radius_km, $boost->age_min, $boost->age_max);
            $boost->update($ids + ['status' => 'active']);
        } catch (Throwable $e) {
            $boost->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000)]);

            return back()->with('status', 'Boost failed: '.$e->getMessage());
        }

        return back()->with('status', 'Boost is live: ₹'.number_format($boost->daily_budget).'/day for '.$boost->days.' day(s).');
    }

    public function publish(Request $request, Content $content): RedirectResponse
    {
        Gate::authorize('markStatus', $content);
        if ($blocker = MetaPublisher::blocker($content)) {
            return back()->with('status', $blocker);
        }
        $at = $request->validate(['publish_at' => ['nullable', 'date', 'after:now']])['publish_at'] ?? null;
        $at = $at ? \Illuminate\Support\Carbon::parse($at) : null;
        $made = MetaPublisher::queue($content, $request->user(), $at);
        if ($made && $at && $content->status === ContentStatus::Approved) {
            $content->update(['status' => ContentStatus::Scheduled]);
        }

        return back()->with('status', match (true) {
            ! $made => 'Already queued or posted.',
            (bool) $at => 'Scheduled for '.$at->format('d M, h:i A').'.',
            default => 'Queued. It will be posted within a minute or two.',
        });
    }

    /** Take back a scheduled post that hasn't gone out yet */
    public function unschedule(Content $content): RedirectResponse
    {
        Gate::authorize('markStatus', $content);
        MetaPost::where('content_id', $content->id)->where('status', 'queued')->where('publish_at', '>', now())->delete();
        if ($content->status === ContentStatus::Scheduled) {
            $content->update(['status' => ContentStatus::Approved]);
        }

        return back()->with('status', 'Schedule cancelled.');
    }

    /** Meta downloads the image / video from this short-lived signed link (no login) */
    public function media(ContentMedia $media): BinaryFileResponse
    {
        return response()->file(Storage::disk('local')->path($media->path));
    }
}
