<?php

namespace App\Http\Controllers;

use App\Enums\ContentStatus;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\CalendarItem;
use App\Models\Client;
use App\Models\MetaAd;
use App\Models\MetaBoost;
use App\Models\MetaConnection;
use App\Models\MetaLead;
use App\Models\MetaPost;
use App\Support\MetaInsights;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Customer reports (monthly, posting, ads, leads): printable pages, "Save as PDF" from the browser (no server PDF library) */
class ReportController extends Controller
{
    public function monthly(Request $request): View
    {
        [$brands, $client, $month] = $this->scope($request);

        $items = $this->items($client, $month);
        $contents = $items->flatMap(fn ($i) => $i->task?->contents ?? []);
        $count = fn (array $statuses) => $contents->filter(fn ($c) => in_array($c->status, $statuses, true))->count();

        return view('reports.monthly', [
            'brands' => $brands,
            'client' => $client,
            'month' => $month,
            'items' => $items,
            'stats' => [
                'Planned posts' => $items->count(),
                'Published' => $count([ContentStatus::Published]),
                'Scheduled' => $count([ContentStatus::Scheduled]),
                'Approved' => $count([ContentStatus::Approved]),
                'In progress' => $items->count() - $count([ContentStatus::Published, ContentStatus::Scheduled, ContentStatus::Approved]),
            ],
        ]);
    }

    /** Posts of the month vs package, with likes / comments / shares / reach from Facebook and Instagram */
    public function posting(Request $request): View
    {
        [$brands, $client, $month] = $this->scope($request);
        $items = $this->items($client, $month);
        $posts = MetaPost::with('page')->where('status', 'done')
            ->whereIn('content_id', $items->flatMap(fn ($i) => $i->task?->contents->pluck('id') ?? []))->get()
            ->map(fn (MetaPost $p) => ['post' => $p, 'stats' => MetaInsights::post($p->page, $p->platform, $p->post_id)])
            ->groupBy(fn ($row) => $row['post']->content_id);

        $all = $posts->flatten(1);
        $sum = fn ($key) => $all->sum(fn ($row) => $row['stats'][$key] ?? 0);

        return view('reports.posting', [
            'brands' => $brands, 'client' => $client, 'month' => $month, 'items' => $items, 'posts' => $posts,
            'quota' => $client->package ? $client->package->static_posts + $client->package->carousel_posts + $client->package->reels : null,
            'published' => $items->filter(fn ($i) => $i->task?->contents->contains('status', ContentStatus::Published))->count(),
            'totals' => ['Reach' => $sum('reach'), 'Likes' => $sum('likes'), 'Comments' => $sum('comments'), 'Shares' => $sum('shares')],
        ]);
    }

    /** Ads and boosts that ran in the month: spend, reach, clicks, results, cost per result */
    public function ads(Request $request): View
    {
        [$brands, $client, $month] = $this->scope($request);
        $from = $month->copy();
        $to = $month->copy()->endOfMonth()->min(today());
        $token = MetaConnection::token();

        $rows = collect();
        if ($token && $from->lte($to)) {
            MetaAd::where('client_id', $client->id)->whereNotNull('ad_id')
                ->whereDate('start_on', '<=', $to)->whereDate('end_on', '>=', $from)->get()
                ->each(function (MetaAd $ad) use ($rows, $token, $from, $to) {
                    foreach (array_filter(['A' => $ad->ad_id, 'B' => $ad->ad_b_id]) as $version => $adId) {
                        $rows->push(['name' => $ad->name.($ad->ad_b_id ? " · $version" : ''), 'goal' => MetaAd::GOALS[$ad->goal],
                            'stats' => MetaInsights::ad($token, $adId, $ad->goal, $from, $to)]);
                    }
                });
            MetaBoost::whereNotNull('ad_id')->where('created_at', '<=', $to->copy()->endOfDay())
                ->whereHas('metaPost.content.task.item.calendar', fn ($q) => $q->where('client_id', $client->id))->get()
                ->filter(fn (MetaBoost $b) => $b->created_at->copy()->addDays($b->days)->gte($from))
                ->each(fn (MetaBoost $b) => $rows->push(['name' => 'Boost · '.$b->created_at->format('d M'), 'goal' => 'Post engagement',
                    'stats' => MetaInsights::ad($token, $b->ad_id, 'boost', $from, $to)]));
        }
        $sum = fn ($key) => $rows->sum(fn ($r) => $r['stats'][$key] ?? 0);

        return view('reports.ads', [
            'brands' => $brands, 'client' => $client, 'month' => $month, 'rows' => $rows, 'connected' => (bool) $token,
            'totals' => ['spend' => $sum('spend'), 'reach' => $sum('reach'), 'clicks' => $sum('clicks'), 'results' => $sum('results')],
        ]);
    }

    /** Lead-form leads of the month: by status and by form */
    public function leads(Request $request): View
    {
        [$brands, $client, $month] = $this->scope($request);
        $leads = MetaLead::where('client_id', $client->id)->whereBetween('lead_at', [$month, $month->copy()->endOfMonth()])->orderBy('lead_at')->get();

        return view('reports.leads', [
            'brands' => $brands, 'client' => $client, 'month' => $month, 'leads' => $leads,
            'byStatus' => collect(MetaLead::STATUSES)->map(fn ($label, $key) => $leads->where('status', $key)->count()),
            'byForm' => $leads->groupBy(fn ($l) => $l->form_name ?: 'Form')->map->count()->sortDesc(),
        ]);
    }

    /** Customer: own brand. Team with "see all tasks": brands within their limit. Month from ?month=YYYY-MM. */
    private function scope(Request $request): array
    {
        $user = $request->user();
        $isCustomer = $user->role === Role::Client;
        abort_unless($isCustomer || $user->hasPermission(Permission::ViewAllTasks), 403);

        $brands = $isCustomer ? Client::whereKey($user->client_id)->get() : Client::orderBy('name')->when($user->brandLimit(), fn ($q, $ids) => $q->whereIn('id', $ids))->get();
        $client = $isCustomer ? $brands->first() : ($brands->firstWhere('id', (int) $request->client) ?? $brands->first());
        abort_unless($client, 404, 'No brand found.');

        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->month)
            ? Carbon::createFromFormat('Y-m-d', $request->month.'-01')->startOfDay()
            : now()->startOfMonth();

        return [$brands, $client, $month];
    }

    private function items(Client $client, Carbon $month)
    {
        return CalendarItem::with('task.contents')
            ->whereBetween('post_date', [$month, $month->copy()->endOfMonth()])
            ->whereHas('calendar', fn ($q) => $q->where('client_id', $client->id))
            ->orderBy('post_date')->get();
    }
}
