<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-bold text-2xl text-gray-900 tracking-tight flex items-center gap-2"><x-icon name="folder" class="w-7 h-7 text-brand-600" /> Assets · {{ $client->name }}</h2>
            @if ($brands->count() > 1)
                <a href="{{ route('assets.index') }}" class="text-sm text-brand-600 hover:underline">‹ All folders</a>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="p-3 rounded bg-green-50 text-green-800 text-sm">{{ session('status') }}</div>
            @endif

            {{-- Folders: the brand's own files, and the files of its Published posts (videos / photos apart) --}}
            <nav class="flex gap-2 overflow-x-auto pb-1 -mx-4 px-4 sm:mx-0 sm:px-0" aria-label="Folder">
                @foreach (['files' => 'Brand files', 'videos' => 'Published videos', 'photos' => 'Published photos'] as $key => $label)
                    <a href="{{ route('assets.index', ['client' => $client->id] + ($key === 'files' ? [] : ['folder' => $key])) }}" @if ($folder === $key) aria-current="page" @endif
                        class="shrink-0 inline-flex items-center gap-2 px-4 min-h-[44px] rounded-full border text-sm font-semibold whitespace-nowrap
                            {{ $folder === $key ? 'bg-brand-600 border-brand-600 text-white' : 'border-gray-300 text-gray-700 hover:bg-gray-50' }}">
                        <x-icon :name="$key === 'videos' ? 'film' : ($key === 'photos' ? 'photo' : 'folder')" class="w-4 h-4" />
                        {{ $label }} <span class="{{ $folder === $key ? 'text-white/80' : 'text-gray-500' }}">{{ $folders[$key] }}</span>
                    </a>
                @endforeach
            </nav>

            @if ($folder === 'files')
                <div class="flex gap-1 p-1 rounded-xl bg-gray-100 w-fit text-sm" role="tablist" aria-label="File type">
                    @foreach ([null => 'All', 'photos' => 'Photos', 'videos' => 'Videos'] as $key => $label)
                        <a href="{{ route('assets.index', array_filter(['client' => $client->id, 'kind' => $key ?: null, 'item' => request('item')])) }}" role="tab" aria-selected="{{ $kind === ($key ?: null) ? 'true' : 'false' }}"
                            class="px-4 min-h-[40px] inline-flex items-center gap-1 rounded-lg font-semibold {{ $kind === ($key ?: null) ? 'bg-white shadow-sm text-gray-900' : 'text-gray-600' }}">
                            {{ $label }} <span class="text-gray-500 font-normal">{{ $key ? $kinds[$key] : $folders['files'] }}</span>
                        </a>
                    @endforeach
                </div>
            <form method="POST" action="{{ route('assets.store') }}" enctype="multipart/form-data" data-split-upload class="card p-4 flex flex-wrap items-end gap-3">
                @csrf
                <input type="hidden" name="client_id" value="{{ $client->id }}">
                <div>
                    <x-input-label value="Upload photos / videos (several at once, max {{ config('filesystems.max_upload_mb') >= 1024 ? config('filesystems.max_upload_mb') / 1024 .' GB' : config('filesystems.max_upload_mb').' MB' }} each)" />
                    <input type="file" name="files[]" multiple required accept="image/*,video/mp4,video/quicktime" class="mt-1 block text-sm">
                    <x-input-error :messages="$errors->get('files')" class="mt-1" />
                    <x-input-error :messages="$errors->get('files.*')" class="mt-1" />
                </div>
                <x-primary-button>Upload</x-primary-button>
                <p class="text-xs text-gray-500 w-full">For a specific post, upload from the "Files" column of that calendar row.</p>
            </form>

            @if (request('item'))
                <p class="text-sm">Showing files of one calendar row · <a href="{{ route('assets.index', ['client' => $client->id]) }}" class="text-brand-600 hover:underline">Show all</a></p>
            @endif

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                @forelse ($assets as $asset)
                    <div class="bg-white shadow-sm rounded-lg overflow-hidden flex flex-col">
                        <div class="aspect-square bg-gray-100 flex items-center justify-center">
                            @if ($asset->isImage())
                                <img src="{{ route('assets.show', $asset) }}" alt="{{ $asset->original_name }}" loading="lazy" class="w-full h-full object-cover">
                            @elseif ($asset->isVideo())
                                <x-video-tile :src="route('assets.show', $asset)" :thumb="$asset->thumb_path ? route('assets.thumb', $asset) : null"
                                    :save="route('assets.thumb.store', $asset)" :name="$asset->original_name" />
                            @endif
                        </div>
                        <div class="p-2 text-xs space-y-1 flex-1" x-data="{ edit: false }">
                            <div x-show="! edit" class="flex items-center gap-1 min-w-0">
                                <span class="font-medium text-gray-800 truncate" title="{{ $asset->original_name }}">{{ $asset->original_name }}</span>
                                @if ($asset->canRename(auth()->user()))
                                    <button type="button" @click="edit = true; $nextTick(() => $refs.name.select())" class="shrink-0 text-brand-600 hover:underline">Rename</button>
                                @endif
                            </div>
                            @if ($asset->canRename(auth()->user()))
                                <form x-show="edit" x-cloak method="POST" action="{{ route('assets.rename', $asset) }}" class="space-y-1" @keydown.escape="edit = false">
                                    @csrf @method('PATCH')
                                    <div class="flex items-center gap-1">
                                        <input x-ref="name" name="name" value="{{ $asset->baseName() }}" required maxlength="200" aria-label="New file name"
                                            class="min-w-0 flex-1 border-gray-300 rounded-lg text-xs py-1">
                                        <span class="text-gray-500 shrink-0">{{ $asset->extension() }}</span>
                                    </div>
                                    <div class="flex gap-2">
                                        <button class="px-2 py-1 rounded-lg bg-brand-600 text-white font-semibold">Save</button>
                                        <button type="button" @click="edit = false" class="px-2 py-1 text-gray-600">Cancel</button>
                                    </div>
                                </form>
                            @endif
                            <div class="text-gray-500">{{ $asset->sizeLabel() }} · {{ $asset->uploader->name }} · {{ $asset->created_at->format('d M') }}</div>
                            @if ($asset->item)
                                <div class="text-purple-700">Post: {{ $asset->item->post_date?->format('d M') ?? 'no date' }}</div>
                            @endif
                        </div>
                        <div class="px-2 pb-2 flex items-center gap-3 text-xs">
                            <a href="{{ route('assets.download', $asset) }}" class="text-brand-600 hover:underline inline-flex items-center gap-1"><x-icon name="download" class="w-4 h-4" /> Download</a>
                            @if ($asset->uploaded_by === auth()->id() || auth()->user()->isAdmin())
                                <form method="POST" action="{{ route('assets.destroy', $asset) }}" onsubmit="return confirm('Delete this file?')">
                                    @csrf @method('DELETE')
                                    <button class="text-red-600 hover:underline">Delete</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="col-span-full text-center text-gray-500 py-8">No files yet.</p>
                @endforelse
            </div>

            {{ $assets->links() }}
            @else
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm text-gray-600">Final files of every Published post, newest first. They update by themselves; nothing to upload here.</p>
                    @if ($months->count() > 1)
                        <form method="GET">
                            <input type="hidden" name="client" value="{{ $client->id }}">
                            <input type="hidden" name="folder" value="{{ $folder }}">
                            <select name="month" onchange="this.form.submit()" aria-label="Month" class="border-gray-300 rounded-xl shadow-sm text-sm min-h-[44px]">
                                <option value="">All months</option>
                                @foreach ($months as $m)
                                    <option value="{{ $m }}" @selected($month === $m)>{{ \Illuminate\Support\Carbon::parse($m.'-01')->format('M Y') }}</option>
                                @endforeach
                            </select>
                        </form>
                    @endif
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                    @forelse ($media as $m)
                        <div class="bg-white shadow-sm rounded-lg overflow-hidden flex flex-col">
                            <div class="aspect-square bg-gray-100 flex items-center justify-center">
                                @if ($m->kind === 'video')
                                    <x-video-tile :src="route('media.show', $m)" :name="$m->original_name" :save="route('media.thumb.store', $m)"
                                        :thumb="$m->thumb_path ? route('media.thumb', $m) : (isset($covers[$m->content_version_id]) ? route('media.show', $covers[$m->content_version_id]) : null)" />
                                @else
                                    <img src="{{ route('media.show', $m) }}" alt="{{ $m->original_name }}" loading="lazy" class="w-full h-full object-cover">
                                @endif
                            </div>
                            <div class="p-2 text-xs space-y-1 flex-1">
                                <div class="font-medium text-gray-800">
                                    {{ $m->post_date ? \Illuminate\Support\Carbon::parse($m->post_date)->format('d M Y') : 'No date' }}
                                    · {{ ucfirst($m->post_type) }}@if ($m->post_type === 'carousel') slide {{ $m->sort_order }}@endif
                                </div>
                                <div class="text-gray-500 truncate" title="{{ $m->original_name }}">{{ $m->original_name }}</div>
                            </div>
                            <div class="px-2 pb-2 flex items-center gap-3 text-xs">
                                <a href="{{ route('media.download', $m) }}" class="text-brand-600 hover:underline inline-flex items-center gap-1"><x-icon name="download" class="w-4 h-4" /> Download</a>
                                <a href="{{ route('contents.show', $m->post_id) }}" class="text-gray-600 hover:underline">Open post</a>
                            </div>
                        </div>
                    @empty
                        <p class="col-span-full text-center text-gray-500 py-8">No published {{ $folder }} yet.</p>
                    @endforelse
                </div>

                {{ $media->links() }}
            @endif
        </div>
    </div>
</x-app-layout>
