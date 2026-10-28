<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-gray-900 tracking-tight">{{ $members->isEmpty() ? 'My Tasks' : 'Tasks' }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <form method="GET" class="flex flex-wrap gap-2">
                @if ($members->isNotEmpty())
                    <select name="member" class="border-gray-300 rounded-xl shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm">
                        <option value="">All members</option>
                        @foreach ($members as $m)
                            <option value="{{ $m->id }}" @selected(request('member') == $m->id)>{{ $m->name }}</option>
                        @endforeach
                    </select>
                @endif
                <select name="status" class="border-gray-300 rounded-xl shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm">
                    @foreach (['pending' => 'Pending', 'submitted' => 'Submitted', 'all' => 'All'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status', 'pending') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-secondary-button type="submit">Filter</x-secondary-button>
            </form>

            {{-- Phone: tappable cards --}}
            <div class="md:hidden space-y-3">
                @forelse ($tasks as $task)
                    @php($item = $task->item)
                    <a href="{{ route('tasks.show', $task) }}" class="card block p-4 active:bg-gray-50 {{ $task->isOverdue() ? 'border-red-200' : '' }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="font-semibold truncate">{{ $item->calendar->client->name }}</div>
                                <div class="text-sm text-gray-600 truncate">{{ $item->typeLabel() }} · post {{ $item->post_date->format('d M') }}</div>
                            </div>
                            <span class="shrink-0 text-xs font-semibold px-2 py-1 rounded-full {{ $task->isOverdue() ? 'bg-red-100 text-red-700' : ($task->deadline->isToday() ? 'bg-accent-100 text-accent-600' : 'bg-gray-100 text-gray-700') }}">
                                {{ $task->isOverdue() ? 'Late' : ($task->deadline->isToday() ? 'Today' : $task->deadline->format('d M')) }}
                            </span>
                        </div>
                        @if ($item->creative_copy)<p class="text-sm text-gray-500 mt-2 line-clamp-2">{{ $item->creative_copy }}</p>@endif
                        <div class="flex items-center justify-between mt-3 text-xs text-gray-500">
                            <span>{{ ucfirst($task->status) }}@if ($members->isNotEmpty()) · {{ $task->assignee->name }}@endif</span>
                            <span class="text-brand-600 font-semibold">Open ›</span>
                        </div>
                    </a>
                @empty
                    <p class="card p-6 text-center text-gray-500">No tasks.</p>
                @endforelse
            </div>

            <div class="card overflow-x-auto hidden md:block">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-3">Deadline</th>
                            <th class="px-4 py-3">Client</th>
                            <th class="px-4 py-3">Post date</th>
                            <th class="px-4 py-3">Post type</th>
                            <th class="px-4 py-3">Creative copy</th>
                            @if ($members->isNotEmpty())<th class="px-4 py-3">Member</th>@endif
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse ($tasks as $task)
                            @php($item = $task->item)
                            <tr class="{{ $task->isOverdue() ? 'bg-red-50' : '' }}">
                                <td class="px-4 py-3 whitespace-nowrap font-medium {{ $task->isOverdue() ? 'text-red-600' : '' }}">
                                    {{ $task->deadline->format('d M, D') }}
                                    @if ($task->isOverdue())<span class="block text-xs">Late</span>@elseif ($task->deadline->isToday())<span class="block text-xs text-orange-600">Today</span>@endif
                                </td>
                                <td class="px-4 py-3">{{ $item->calendar->client->name }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $item->post_date->format('d M') }}</td>
                                <td class="px-4 py-3">{{ $item->typeLabel() }}</td>
                                <td class="px-4 py-3 max-w-xs">{{ Str::limit($item->creative_copy, 80) }}</td>
                                @if ($members->isNotEmpty())<td class="px-4 py-3">{{ $task->assignee->name }}</td>@endif
                                <td class="px-4 py-3">{{ ucfirst($task->status) }}</td>
                                <td class="px-4 py-3 text-right"><a href="{{ route('tasks.show', $task) }}" class="text-brand-600 hover:underline">Open</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-6 text-center text-gray-500">No tasks.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $tasks->links() }}
        </div>
    </div>
</x-app-layout>
