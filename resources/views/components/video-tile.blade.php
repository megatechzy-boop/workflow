@props(['src', 'thumb' => null, 'save' => null, 'name' => 'video'])
{{-- A picture of the video; the video itself loads only on Play (resources/js/video-tile.js).
     No picture yet: the browser takes a frame once and saves it ($save). --}}
<div data-video-tile data-src="{{ $src }}" @if (! $thumb && $save) data-save="{{ $save }}" @endif class="relative w-full h-full bg-gray-800 text-gray-300 flex items-center justify-center">
    @if ($thumb)
        <img src="{{ $thumb }}" alt="{{ $name }}" loading="lazy" class="absolute inset-0 w-full h-full object-cover">
    @else
        <x-icon name="film" class="w-10 h-10" data-placeholder />
    @endif
    <button type="button" data-play aria-label="Play {{ $name }}" class="absolute inset-0 flex items-center justify-center group">
        <span class="rounded-full bg-black/60 group-hover:bg-black/80 text-white p-3"><x-icon name="play" class="w-6 h-6" /></span>
    </button>
</div>
