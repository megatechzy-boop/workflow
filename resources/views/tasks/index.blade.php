<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $members->isEmpty() ? 'My Tasks' : 'Tasks' }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <form method="GET" class="flex flex-wrap gap-2">
                @if ($members->isNotEmpty())
                    <select name="member" class="border-gray-300 rounded-md shadow-sm text-sm">
                        <option value="">Sab members</option>
                        @foreach ($members as $m)
                            <option value="{{ $m->id }}" @selected(request('member') == $m->id)>{{ $m->name }}</option>
                        @endforeach
                    </select>
                @endif
                <select name="status" class="border-gray-300 rounded-md shadow-sm text-sm">
                    @foreach (['pending' => 'Pending', 'submitted' => 'Submitted', 'all' => 'Sab'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status', 'pending') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-secondary-button type="submit">Filter</x-secondary-button>
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
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
                                    @if ($task->isOverdue())<span class="block text-xs">Late</span>@elseif ($task->deadline->isToday())<span class="block text-xs text-orange-600">Aaj</span>@endif
                                </td>
                                <td class="px-4 py-3">{{ $item->calendar->client->name }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $item->post_date->format('d M') }}</td>
                                <td class="px-4 py-3">{{ $item->typeLabel() }}</td>
                                <td class="px-4 py-3 max-w-xs">{{ Str::limit($item->creative_copy, 80) }}</td>
                                @if ($members->isNotEmpty())<td class="px-4 py-3">{{ $task->assignee->name }}</td>@endif
                                <td class="px-4 py-3">{{ ucfirst($task->status) }}</td>
                                <td class="px-4 py-3 text-right"><a href="{{ route('calendars.show', $item->calendar) }}" class="text-indigo-600 hover:underline">Brief dekho</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-6 text-center text-gray-500">Koi task nahi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $tasks->links() }}
        </div>
    </div>
</x-app-layout>
