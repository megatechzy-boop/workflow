<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-2xl text-gray-900 tracking-tight">Packages</h2>
            <a href="{{ route('admin.packages.create') }}" class="inline-flex items-center px-4 py-2 bg-brand-600 rounded-xl text-sm font-semibold text-white hover:bg-brand-700 min-h-[44px]">+ New Package</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="p-3 rounded bg-green-50 text-green-800 text-sm">{{ session('status') }}</div>
            @endif

            <div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
                @forelse ($packages as $package)
                    <div class="card p-5 flex flex-col {{ $package->is_active ? '' : 'opacity-60' }}">
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="font-semibold text-gray-900">{{ $package->name }}</h3>
                            @unless ($package->is_active)
                                <span class="text-xs text-gray-500">Inactive</span>
                            @endunless
                        </div>
                        <div class="mt-1 text-2xl font-bold text-gray-900">{{ $package->priceLabel() }}<span class="text-sm font-normal text-gray-500"> / month</span></div>
                        @if ($package->min_months)
                            <div class="text-xs text-accent-600">{{ $package->min_months }}-month minimum</div>
                        @endif
                        <div class="mt-3 text-sm text-gray-600">{{ $package->static_posts }} static · {{ $package->carousel_posts }} carousel · {{ $package->reels }} reels</div>
                        <div class="text-sm text-gray-500">{{ $package->platformLabel() }}</div>
                        <ul class="mt-3 space-y-1 text-sm text-gray-700 flex-1">
                            @foreach ($package->services as $service)
                                <li class="flex gap-2"><x-icon name="tick" class="w-4 h-4 mt-0.5 text-brand-600 shrink-0" />{{ $service }}</li>
                            @endforeach
                        </ul>
                        <div class="mt-4 pt-3 border-t border-gray-100 flex items-center justify-between text-sm">
                            <span class="text-gray-500">{{ $package->clients_count }} {{ Str::plural('client', $package->clients_count) }}</span>
                            <span class="space-x-3">
                                <a href="{{ route('admin.packages.edit', $package) }}" class="text-brand-600 hover:underline">Edit</a>
                                <form method="POST" action="{{ route('admin.packages.destroy', $package) }}" class="inline" onsubmit="return confirm('Delete this package?')">
                                    @csrf @method('DELETE')
                                    <button class="text-red-600 hover:underline">Delete</button>
                                </form>
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="card p-6 text-center text-gray-500 sm:col-span-2 xl:col-span-4">No packages yet.</div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
