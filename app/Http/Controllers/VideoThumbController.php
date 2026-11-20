<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\ContentMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Video preview pictures: the first browser that opens the Assets grid takes a frame of the video
 * and sends it here (no ffmpeg on the host); everyone after sees the picture instead of loading the video.
 */
class VideoThumbController extends Controller
{
    public function showAsset(Request $request, Asset $asset): BinaryFileResponse
    {
        abort_unless(Asset::canAccessBrand($request->user(), $asset->client_id), 403);

        return $this->show($asset);
    }

    public function storeAsset(Request $request, Asset $asset): Response
    {
        abort_unless(Asset::canAccessBrand($request->user(), $asset->client_id) && $asset->isVideo(), 403);

        return $this->store($request, $asset, "thumbs/asset-{$asset->id}.jpg");
    }

    public function showMedia(ContentMedia $media): BinaryFileResponse
    {
        Gate::authorize('view', $media->version->content);

        return $this->show($media);
    }

    public function storeMedia(Request $request, ContentMedia $media): Response
    {
        Gate::authorize('view', $media->version->content);
        abort_unless($media->kind === 'video', 403);

        return $this->store($request, $media, "thumbs/media-{$media->id}.jpg");
    }

    private function show(Model $file): BinaryFileResponse
    {
        abort_unless($file->thumb_path && Storage::disk('local')->exists($file->thumb_path), 404);

        return response()->file(Storage::disk('local')->path($file->thumb_path), ['Cache-Control' => 'private, max-age=2592000']);
    }

    /** The first picture is kept (two people opening the page at once: one wins) */
    private function store(Request $request, Model $file, string $path): Response
    {
        $request->validate(['thumb' => ['required', 'file', 'mimetypes:image/jpeg', 'max:1024']]);
        if (! $file->thumb_path) {
            Storage::disk('local')->put($path, $request->file('thumb')->get());
            $file->forceFill(['thumb_path' => $path])->save();
        }

        return response()->noContent();
    }
}
