<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Approvals</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <form method="GET" class="flex flex-wrap gap-2">
                <select name="status" class="border-gray-300 rounded-md shadow-sm text-sm">
                    <option value="pending" @selected(request('status', 'pending') === 'pending')>Review pending</option>
                    @foreach ($statuses as $s)
                        <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
                    @endforeach
                    <option value="all" @selected(request('status') === 'all')>Sab</option>
                </select>
                <x-secondary-button type="submit">Filter</x-secondary-button>
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
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
                                <td class="px-4 py-3 text-right"><a href="{{ route('contents.show', $content) }}" class="text-indigo-600 hover:underline">Open</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">Kuch pending nahi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $contents->links() }}
        </div>
    </div>
</x-app-layout>
