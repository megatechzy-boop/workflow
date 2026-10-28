<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Asset;
use App\Models\CalendarItem;
use App\Models\Client;
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
        $brands = $isCustomer ? Client::whereKey($user->client_id)->get() : Client::orderBy('name')->get();
        $client = $isCustomer ? $brands->first() : ($brands->firstWhere('id', (int) $request->client) ?? $brands->first());
        abort_unless($client, 404, 'No brand found.');

        $assets = Asset::with(['uploader', 'item'])
            ->where('client_id', $client->id)
            ->when($request->item, fn ($q, $id) => $q->where('calendar_item_id', $id))
            ->latest()
            ->paginate(40)
            ->withQueryString();

        return view('assets.index', compact('brands', 'client', 'assets'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'calendar_item_id' => ['nullable', 'exists:calendar_items,id'],
            'files' => ['required', 'array', 'max:20'],
            // Photos + videos, max 100 MB per file
            'files.*' => ['file', 'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/quicktime', 'max:102400'],
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

        return back()->with('status', count($stored).' file(s) uploaded.');
    }

    /** Preview (image/video inline) */
    public function show(Request $request, Asset $asset): BinaryFileResponse
    {
        abort_unless(Asset::canAccessBrand($request->user(), $asset->client_id), 403);

        return response()->file(Storage::disk('local')->path($asset->path));
    }

    public function download(Request $request, Asset $asset): BinaryFileResponse
    {
        abort_unless(Asset::canAccessBrand($request->user(), $asset->client_id), 403);

        return response()->download(Storage::disk('local')->path($asset->path), $asset->original_name);
    }

    /** The uploader or an Admin can delete */
    public function destroy(Request $request, Asset $asset): RedirectResponse
    {
        abort_unless($asset->uploaded_by === $request->user()->id || $request->user()->isAdmin(), 403);

        $asset->delete();

        return back()->with('status', 'File deleted.');
    }
}
