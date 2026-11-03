<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-gray-900 tracking-tight flex items-center gap-2"><x-icon name="folder" class="w-7 h-7 text-brand-600" /> Assets</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                @foreach ($brands as $b)
                    <a href="{{ route('assets.index', ['client' => $b->id]) }}" class="card p-4 flex flex-col items-center text-center gap-2 hover:border-brand-300 active:bg-gray-50">
                        <x-icon name="folder" class="w-12 h-12 text-accent-500" />
                        <span class="font-semibold text-gray-900 break-words w-full">{{ $b->name }}</span>
                        <span class="text-xs text-gray-500">{{ $counts[$b->id] ?? 0 }} file(s)</span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
