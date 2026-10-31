<?php

namespace App\Http\Controllers;

use App\Models\Content;
use App\Models\ContentMedia;
use App\Models\MetaPage;
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
        return view('meta.index', ['pages' => MetaPage::orderBy('name')->get(), 'ready' => (bool) config('services.meta.app_id')]);
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
            $pages = MetaGraph::pages(MetaGraph::userToken($request->input('code')));
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('admin.meta.index')->with('status', 'Facebook said: '.$e->getMessage());
        }
        foreach ($pages as $page) {
            MetaPage::updateOrCreate(['page_id' => $page['page_id']], $page);
        }

        return redirect()->route('admin.meta.index')->with('status', count($pages).' Page(s) connected. Now link each client to its Page (Clients > Edit).');
    }

    public function publish(Request $request, Content $content): RedirectResponse
    {
        Gate::authorize('markStatus', $content);
        if ($blocker = MetaPublisher::blocker($content)) {
            return back()->with('status', $blocker);
        }
        $made = MetaPublisher::queue($content, $request->user());

        return back()->with('status', $made ? 'Queued. It will be posted within a minute or two.' : 'Already queued or posted.');
    }

    /** Meta downloads the image / video from this short-lived signed link (no login) */
    public function media(ContentMedia $media): BinaryFileResponse
    {
        return response()->file(Storage::disk('local')->path($media->path));
    }
}
