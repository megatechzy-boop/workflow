<?php

namespace App\Http\Controllers;

use App\Enums\ContentStatus;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Asset;
use App\Models\CalendarItem;
use App\Models\Client;
use App\Models\ContentMedia;
use App\Models\User;
use App\Support\Notify;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Brand library: customer/team photos and videos (can be linked to a calendar row) */
class AssetController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $isCustomer = $user->role === Role::Client;
        $brands = $isCustomer ? Client::whereKey($user->client_id)->get() : Client::orderBy('name')->when($user->brandLimit(), fn ($q, $ids) => $q->whereIn('id', $ids))->get();
        abort_if($brands->isEmpty(), 404, 'No brand found.');

        // Several brands and none picked: one folder per brand instead of a dropdown
        if ($brands->count() > 1 && ! $request->client) {
            $counts = Asset::whereIn('client_id', $brands->modelKeys())->selectRaw('client_id, COUNT(*) as n')->groupBy('client_id')->pluck('n', 'client_id');

            return view('assets.folders', compact('brands', 'counts'));
        }
        $client = $isCustomer ? $brands->first() : ($brands->firstWhere('id', (int) $request->client) ?? $brands->first());

        // Published posts' files (final version), in their own folders: videos (reels) and photos (posts + carousel slides)
        $published = fn (string $kind) => ContentMedia::query()
            ->select('content_media.*', 'items.post_date as post_date', 'contents.post_type as post_type', 'contents.id as post_id')
            ->join('content_versions as versions', 'versions.id', '=', 'content_media.content_version_id')
            ->join('contents', fn ($j) => $j->on('contents.id', '=', 'versions.content_id')->on('versions.number', '=', 'contents.current_version'))
            ->join('tasks', 'tasks.id', '=', 'contents.task_id')
            ->join('calendar_items as items', 'items.id', '=', 'tasks.calendar_item_id')
            ->join('calendars', 'calendars.id', '=', 'items.calendar_id')
            ->where('calendars.client_id', $client->id)
            ->where('contents.status', ContentStatus::Published->value)
            ->where('content_media.kind', $kind);
        $folders = ['files' => Asset::where('client_id', $client->id)->count(), 'videos' => $published('video')->count(), 'photos' => $published('image')->count()];
        $folder = in_array($request->folder, ['videos', 'photos'], true) ? $request->folder : 'files';
        // Brand files split by kind: All / Photos / Videos (raw footage)
        $kinds = ['photos' => Asset::where('client_id', $client->id)->where('mime', 'like', 'image/%')->count(),
            'videos' => Asset::where('client_id', $client->id)->where('mime', 'like', 'video/%')->count()];
        $kind = in_array($request->kind, ['photos', 'videos'], true) ? $request->kind : null;

        if (in_array($folder, ['videos', 'photos'], true)) {
            $query = $published($folder === 'videos' ? 'video' : 'image');
            $months = (clone $query)->pluck('post_date')->filter()->map(fn ($d) => substr((string) $d, 0, 7))->unique()->sortDesc()->values();
            $month = $months->contains($request->month) ? $request->month : null;
            $media = $query
                ->when($month, fn ($q) => $q->whereBetween('items.post_date', [$month.'-01', \Illuminate\Support\Carbon::parse($month.'-01')->endOfMonth()->toDateString().' 23:59:59']))
                ->orderByDesc('items.post_date')->orderByDesc('contents.id')->orderBy('content_media.sort_order')
                ->paginate(40)->withQueryString();
            // A reel's cover is its video's picture
            $covers = ContentMedia::whereIn('content_version_id', $media->pluck('content_version_id'))->where('kind', 'cover')->pluck('id', 'content_version_id');

            return view('assets.index', compact('brands', 'client', 'folders', 'folder', 'media', 'months', 'month', 'covers'));
        }

        $assets = Asset::with(['uploader', 'item'])
            ->where('client_id', $client->id)
            ->when($request->item, fn ($q, $id) => $q->where('calendar_item_id', $id))
            ->when($kind, fn ($q, $k) => $q->where('mime', 'like', ($k === 'photos' ? 'image' : 'video').'/%'))
            ->latest()
            ->paginate(40)
            ->withQueryString();

        return view('assets.index', compact('brands', 'client', 'assets', 'folders', 'folder', 'kinds', 'kind'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'calendar_item_id' => ['nullable', 'exists:calendar_items,id'],
            'files' => ['required', 'array', 'max:20'],
            // Photos + videos, up to MAX_UPLOAD_MB (1 GB) per file
            'files.*' => ['file', 'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/quicktime', 'max:'.config('filesystems.max_upload_mb') * 1024],
        ], ['files.*.mimetypes' => 'Only photos (jpg/png/webp/gif) or videos (mp4/mov) are allowed.']);

        $clientId = (int) $data['client_id'];
        abort_unless(Asset::canAccessBrand($user, $clientId), 403);
        if (! empty($data['calendar_item_id'])) {
            // The row must belong to the same brand
            abort_unless(CalendarItem::find($data['calendar_item_id'])->calendar->client_id === $clientId, 422);
        }

        $stored = [];
        try {
            DB::transaction(function () use ($request, $data, $clientId, $user, &$stored) {
                foreach ($request->file('files') as $file) {
                    $stored[] = $path = $file->store("assets/{$clientId}", 'local');
                    Asset::create([
                        'client_id' => $clientId,
                        'calendar_item_id' => $data['calendar_item_id'] ?? null,
                        'uploaded_by' => $user->id,
                        'path' => $path,
                        'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                        'mime' => $file->getMimeType(),
                        'size' => $file->getSize(),
                    ]);
                }
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($stored); // remove the files if the DB write fails
            throw $e;
        }

        // Several files are sent one per request ("X-Upload-Part: 2/4"): tell the team once, after the last one
        [$part, $parts] = array_map('intval', explode('/', $request->header('X-Upload-Part', '1/1')) + [1 => 1]);
        $total = $parts > 1 ? $parts : count($stored);
        if ($user->role === Role::Client && $part >= $parts) {
            $this->notifyTeam($clientId, $data['calendar_item_id'] ?? null, $total);
        }

        return back()->with('status', $total.' file(s) uploaded.');
    }

    /** Customer uploaded files => tell the row's Designer/Editor + calendar owner (or the planners for the library) */
    private function notifyTeam(int $clientId, ?int $itemId, int $count): void
    {
        $brand = Client::find($clientId)->name;
        $item = $itemId ? CalendarItem::with(['task.assignee', 'calendar.creator'])->find($itemId) : null;

        if ($item) {
            Notify::send(array_filter([$item->task?->assignee, $item->calendar->creator]),
                'Customer sent files', "$brand uploaded $count file(s) for the ".($item->post_date?->format('d M') ?? 'undated').' post.',
                route('calendars.show', $item->calendar));
        } else {
            Notify::send(User::withPermission(Permission::ManageCalendars),
                'Customer sent files', "$brand uploaded $count file(s) to the brand library.",
                route('assets.index', ['client' => $clientId]));
        }
    }

    /** Preview (image/video inline) */
    public function show(Request $request, Asset $asset): BinaryFileResponse
    {
        abort_unless($asset->canOpen($request->user()), 403);

        return response()->file(Storage::disk('local')->path($asset->path));
    }

    public function download(Request $request, Asset $asset): BinaryFileResponse
    {
        abort_unless($asset->canOpen($request->user()), 403);

        return response()->download(Storage::disk('local')->path($asset->path), $asset->original_name);
    }

    /** New file name (the extension stays) */
    public function rename(Request $request, Asset $asset): RedirectResponse
    {
        abort_unless($asset->canRename($request->user()), 403);
        $name = trim($request->validate(['name' => ['required', 'string', 'max:200']])['name']);
        $name = trim(preg_replace('~[\\\\/:*?"<>|\x00-\x1F]+~', ' ', $name)); // no characters a file name cannot hold
        if ($name === '') {
            return back()->withErrors(['name' => 'Type a name.']);
        }
        if ($asset->extension() !== '' && str_ends_with(mb_strtolower($name), mb_strtolower($asset->extension()))) {
            $name = mb_substr($name, 0, -mb_strlen($asset->extension())); // typed with the extension: not twice
        }
        $asset->update(['original_name' => mb_substr($name, 0, 240).$asset->extension()]);

        return back()->with('status', 'File renamed.');
    }

    /** The uploader or an Admin can delete */
    public function destroy(Request $request, Asset $asset): RedirectResponse
    {
        abort_unless($asset->uploaded_by === $request->user()->id || $request->user()->isAdmin(), 403);

        $asset->delete();

        return back()->with('status', 'File deleted.');
    }
}
