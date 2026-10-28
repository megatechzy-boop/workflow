<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-2xl text-gray-900 tracking-tight">Content Calendars</h2>
            @can('create', App\Models\Calendar::class)
                <a href="{{ route('calendars.create') }}" class="inline-flex items-center px-4 py-2 bg-brand-600 rounded-xl text-sm font-semibold text-white hover:bg-brand-700 min-h-[44px]">+ New Calendar</a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="p-3 rounded bg-green-50 text-green-800 text-sm">{{ session('status') }}</div>
            @endif

            <form method="GET" class="flex flex-wrap gap-2">
                @if ($clients->isNotEmpty())
                    <select name="client_id" class="border-gray-300 rounded-xl shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm">
                        <option value="">All clients</option>
                        @foreach ($clients as $c)
                            <option value="{{ $c->id }}" @selected(request('client_id') == $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                @endif
                <select name="status" class="border-gray-300 rounded-xl shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $s)
                        <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
                    @endforeach
                </select>
                <x-secondary-button type="submit">Filter</x-secondary-button>
            </form>

            {{-- Phone: tappable cards --}}
            <div class="md:hidden space-y-3">
                @forelse ($calendars as $calendar)
                    <a href="{{ route('calendars.show', $calendar) }}" class="card flex items-center justify-between gap-3 p-4 active:bg-gray-50">
                        <div class="min-w-0">
                            <div class="font-semibold truncate">{{ $calendar->client->name }}</div>
                            <div class="text-sm text-gray-600">{{ $calendar->month->format('F Y') }} · {{ $calendar->items_count }} posts</div>
                        </div>
                        <span class="shrink-0 px-2 py-1 rounded-full text-xs font-semibold {{ $calendar->status->color() }}">{{ $calendar->status->label() }}</span>
                    </a>
                @empty
                    <p class="card p-6 text-center text-gray-500">No calendars yet.</p>
                @endforelse
            </div>

            <div class="card overflow-x-auto hidden md:block">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-3">Client</th>
                            <th class="px-4 py-3">Month</th>
                            <th class="px-4 py-3">Posts</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse ($calendars as $calendar)
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ $calendar->client->name }}</td>
                                <td class="px-4 py-3">{{ $calendar->month->format('F Y') }}</td>
                                <td class="px-4 py-3">{{ $calendar->items_count }}</td>
                                <td class="px-4 py-3"><span class="px-2 py-1 rounded text-xs {{ $calendar->status->color() }}">{{ $calendar->status->label() }}</span></td>
                                <td class="px-4 py-3 text-right"><a href="{{ route('calendars.show', $calendar) }}" class="text-brand-600 hover:underline">Open</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">No calendars yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $calendars->links() }}
        </div>
    </div>
</x-app-layout>
