<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-gray-900 tracking-tight">Approvals</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <form method="GET" class="flex flex-wrap gap-2">
                <select name="status" class="border-gray-300 rounded-xl shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm">
                    <option value="pending" @selected(request('status', 'pending') === 'pending')>Review pending</option>
                    @foreach ($statuses as $s)
                        <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
                    @endforeach
                    <option value="all" @selected(request('status') === 'all')>All</option>
                </select>
                <x-secondary-button type="submit">Filter</x-secondary-button>
            </form>

            {{-- Phone: tappable cards --}}
            <div class="md:hidden space-y-3">
                @forelse ($contents as $content)
                    <a href="{{ route('contents.show', $content) }}" class="card block p-4 active:bg-gray-50">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="font-semibold truncate">{{ $content->task->item->calendar->client->name }}</div>
                                <div class="text-sm text-gray-600 truncate">{{ $content->typeLabel() }}</div>
                            </div>
                            <span class="shrink-0 px-2 py-1 rounded-full text-xs font-semibold {{ $content->status->color() }}">{{ $content->status->label() }}</span>
                        </div>
                        <div class="flex items-center justify-between mt-3 text-xs text-gray-500">
                            <span>Post {{ $content->task->item->post_date->format('d M') }} · V{{ $content->current_version }} · {{ $content->task->assignee->name }}</span>
                            <span class="text-brand-600 font-semibold">Open ›</span>
                        </div>
                    </a>
                @empty
                    <p class="card p-6 text-center text-gray-500">Nothing pending.</p>
                @endforelse
            </div>

            <div class="card overflow-x-auto hidden md:block">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-3">Client</th>
                            <th class="px-4 py-3">Post date</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Member</th>
                            <th class="px-4 py-3">Approver</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse ($contents as $content)
                            <tr>
                                <td class="px-4 py-3">{{ $content->task->item->calendar->client->name }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $content->task->item->post_date->format('d M') }}</td>
                                <td class="px-4 py-3">{{ $content->typeLabel() }}</td>
                                <td class="px-4 py-3">{{ $content->task->assignee->name }}</td>
                                <td class="px-4 py-3">{{ $content->approver->name }}</td>
                                <td class="px-4 py-3"><span class="px-2 py-1 rounded text-xs {{ $content->status->color() }}">{{ $content->status->label() }} · V{{ $content->current_version }}</span></td>
                                <td class="px-4 py-3 text-right"><a href="{{ route('contents.show', $content) }}" class="text-brand-600 hover:underline">Open</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">Nothing pending.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $contents->links() }}
        </div>
    </div>
</x-app-layout>
