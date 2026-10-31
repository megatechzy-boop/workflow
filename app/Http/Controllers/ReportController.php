<?php

namespace App\Http\Controllers;

use App\Enums\ContentStatus;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\CalendarItem;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Customer monthly report: printable page, "Save as PDF" from the browser (no server PDF library) */
class ReportController extends Controller
{
    public function monthly(Request $request): View
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

        $items = CalendarItem::with('task.contents')
            ->whereBetween('post_date', [$month, $month->copy()->endOfMonth()])
            ->whereHas('calendar', fn ($q) => $q->where('client_id', $client->id))
            ->orderBy('post_date')->get();

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
}
