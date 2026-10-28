<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Content Calendars</h2>
            @can('create', App\Models\Calendar::class)
                <a href="{{ route('calendars.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md text-xs font-semibold text-white uppercase tracking-widest hover:bg-gray-700">+ New Calendar</a>
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
                    <select name="client_id" class="border-gray-300 rounded-md shadow-sm text-sm">
                        <option value="">All clients</option>
                        @foreach ($clients as $c)
                            <option value="{{ $c->id }}" @selected(request('client_id') == $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                @endif
                <select name="status" class="border-gray-300 rounded-md shadow-sm text-sm">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $s)
                        <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
                    @endforeach
                </select>
                <x-secondary-button type="submit">Filter</x-secondary-button>
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
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
                                <td class="px-4 py-3 text-right"><a href="{{ route('calendars.show', $calendar) }}" class="text-indigo-600 hover:underline">Open</a></td>
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
