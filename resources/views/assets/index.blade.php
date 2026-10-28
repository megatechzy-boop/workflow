<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-bold text-2xl text-gray-900 tracking-tight flex items-center gap-2"><x-icon name="folder" class="w-7 h-7 text-brand-600" /> Assets · {{ $client->name }}</h2>
            @if ($brands->count() > 1)
                <form method="GET">
                    <select name="client" onchange="this.form.submit()" class="border-gray-300 rounded-xl shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm" aria-label="Choose brand">
                        @foreach ($brands as $b)
                            <option value="{{ $b->id }}" @selected($b->id === $client->id)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="p-3 rounded bg-green-50 text-green-800 text-sm">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('assets.store') }}" enctype="multipart/form-data" class="card p-4 flex flex-wrap items-end gap-3">
                @csrf
                <input type="hidden" name="client_id" value="{{ $client->id }}">
                <div>
                    <x-input-label value="Upload photos / videos (several at once, max 100 MB each)" />
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
                                <video src="{{ route('assets.show', $asset) }}" preload="metadata" controls class="w-full h-full object-cover"></video>
                            @endif
                        </div>
                        <div class="p-2 text-xs space-y-1 flex-1">
                            <div class="font-medium text-gray-800 truncate" title="{{ $asset->original_name }}">{{ $asset->original_name }}</div>
                            <div class="text-gray-500">{{ $asset->sizeLabel() }} · {{ $asset->uploader->name }} · {{ $asset->created_at->format('d M') }}</div>
                            @if ($asset->item)
                                <div class="text-purple-700">Post: {{ $asset->item->post_date->format('d M') }}</div>
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
        </div>
    </div>
</x-app-layout>
