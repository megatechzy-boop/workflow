<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $calendar->client->name }} · {{ $calendar->month->format('F Y') }}</h2>
                <p class="text-sm text-gray-500 mt-1">Banaya: {{ $calendar->creator->name }}</p>
            </div>
            <span class="px-3 py-1 rounded text-sm {{ $calendar->status->color() }}">{{ $calendar->status->label() }}</span>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-[1600px] mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="p-3 rounded bg-green-50 text-green-800 text-sm">{{ session('status') }}</div>
            @endif

            @php($lastRevision = $calendar->reviews->firstWhere('action', 'revision'))
            @if ($calendar->status === App\Enums\CalendarStatus::Draft && $lastRevision)
                <div class="p-4 rounded bg-orange-50 border border-orange-200 text-sm">
                    <strong>Revision chahiye</strong> ({{ $lastRevision->user->name }}, {{ $lastRevision->created_at->format('d M, h:i A') }}):
                    <p class="mt-1 whitespace-pre-line">{{ $lastRevision->comment }}</p>
                </div>
            @endif

            {{-- Actions --}}
            <div class="flex flex-wrap items-center gap-3">
                @can('update', $calendar)
                    <a href="{{ route('calendars.items.create', $calendar) }}" class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md text-xs font-semibold text-white uppercase tracking-widest hover:bg-gray-700">+ Add Post</a>
                @endcan
                @can('submit', $calendar)
                    <form method="POST" action="{{ route('calendars.submit', $calendar) }}">
                        @csrf
                        <x-primary-button>Submit for Approval</x-primary-button>
                    </form>
                @endcan
                @can('delete', $calendar)
                    <form method="POST" action="{{ route('calendars.destroy', $calendar) }}" onsubmit="return confirm('Poora calendar delete karna hai?')">
                        @csrf @method('DELETE')
                        <button class="text-sm text-red-600 hover:underline">Delete calendar</button>
                    </form>
                @endcan
            </div>

            {{-- Items table --}}
            {{-- Sheet jaisa look: grid borders, light blue cells, centered text --}}
            <div class="shadow-sm overflow-x-auto border border-gray-800">
                <table class="min-w-full text-sm border-collapse bg-[#eef3fb] text-gray-900 [&_td]:border [&_td]:border-gray-400 [&_th]:border [&_th]:border-gray-800">
                    <thead class="bg-[#dfe8f7] text-center font-bold text-base">
                        <tr>
                            <th class="px-3 py-3 w-16">Sr. No.</th>
                            <th class="px-3 py-3 w-36">Date to post</th>
                            <th class="px-3 py-3 w-36">Post type</th>
                            <th class="px-3 py-3 min-w-[380px]">Creative copy for post</th>
                            <th class="px-3 py-3 min-w-[320px]">Visual brief</th>
                            <th class="px-3 py-3 min-w-[160px]">Brief about the post</th>
                            <th class="px-3 py-3 min-w-[120px]">Reference link, if any</th>
                            <th class="px-3 py-3 min-w-[140px]">Remarks</th>
                            @if ($showTasks)
                                <th class="px-3 py-3 min-w-[170px] bg-[#fff4d6]">Assigned to</th>
                                <th class="px-3 py-3 min-w-[110px] bg-[#fff4d6]">Deadline</th>
                            @endif
                            @can('update', $calendar)<th class="px-3 py-3"></th>@endcan
                        </tr>
                    </thead>
                    <tbody class="text-center align-middle">
                        @forelse ($calendar->items as $item)
                            <tr>
                                <td class="px-3 py-4 text-blue-800">{{ $loop->iteration }}</td>
                                <td class="px-3 py-4">{{ $item->post_date->format('d M l') }}</td>
                                <td class="px-3 py-4">{{ $item->typeLabel() }}</td>
                                <td class="px-4 py-4 whitespace-pre-line">{{ $item->creative_copy }}</td>
                                <td class="px-4 py-4 whitespace-pre-line">{{ $item->visual_brief }}</td>
                                <td class="px-3 py-4 whitespace-pre-line">{{ $item->brief }}</td>
                                <td class="px-3 py-4">
                                    @if ($item->reference_link)
                                        <a href="{{ $item->reference_link }}" target="_blank" rel="noopener noreferrer" class="text-indigo-700 underline break-all">Link</a>
                                    @endif
                                </td>
                                <td class="px-3 py-4 whitespace-pre-line">{{ $item->remarks }}</td>
                                @if ($showTasks)
                                    <td class="px-3 py-4 bg-[#fffaeb]">
                                        @can('assign', $calendar)
                                            <select name="assign[{{ $item->id }}]" form="assign-form" class="border-gray-300 rounded-md shadow-sm text-sm w-full">
                                                <option value="">— Member —</option>
                                                @foreach ($members as $m)
                                                    <option value="{{ $m->id }}" @selected($item->task?->assigned_to === $m->id)>{{ $m->name }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            {{ $item->task?->assignee->name ?? '—' }}
                                        @endcan
                                    </td>
                                    <td class="px-3 py-4 bg-[#fffaeb] whitespace-nowrap {{ $item->task?->isOverdue() ? 'text-red-600 font-semibold' : '' }}">
                                        {{ $item->task?->deadline->format('d M') ?? '—' }}
                                    </td>
                                @endif
                                @can('update', $calendar)
                                    <td class="px-3 py-4 whitespace-nowrap space-x-2">
                                        <a href="{{ route('calendars.items.edit', [$calendar, $item]) }}" class="text-indigo-600 hover:underline">Edit</a>
                                        <form method="POST" action="{{ route('calendars.items.destroy', [$calendar, $item]) }}" class="inline" onsubmit="return confirm('Ye post hatana hai?')">
                                            @csrf @method('DELETE')
                                            <button class="text-red-600 hover:underline">Delete</button>
                                        </form>
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-4 py-6 text-center text-gray-500">Abhi koi post nahi. "+ Add Post" se shuru karo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @can('assign', $calendar)
                <form id="assign-form" method="POST" action="{{ route('calendars.assign', $calendar) }}" class="flex items-center gap-3">
                    @csrf
                    <x-primary-button>Save Assignments</x-primary-button>
                    <span class="text-sm text-gray-500">Deadline apne aap: posting date se 2 din pehle.</span>
                </form>
            @endcan

            {{-- Review box: Approver (Submitted) ya Client (Approver OK) --}}
            @can('review', $calendar)
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-3">Aapka review</h3>
                    <form method="POST" action="{{ route('calendars.review', $calendar) }}" class="space-y-3">
                        @csrf
                        <textarea name="comment" rows="3" placeholder="Comment (Revision ke liye zaroori)" class="block w-full border-gray-300 rounded-md shadow-sm">{{ old('comment') }}</textarea>
                        <x-input-error :messages="$errors->get('comment')" />
                        <div class="flex gap-3">
                            <button name="action" value="approved" class="px-4 py-2 rounded-md bg-green-600 text-white text-sm font-semibold hover:bg-green-500">Approve</button>
                            <button name="action" value="revision" class="px-4 py-2 rounded-md bg-orange-500 text-white text-sm font-semibold hover:bg-orange-400">Revision</button>
                        </div>
                    </form>
                </div>
            @endcan

            {{-- History --}}
            @if ($calendar->reviews->isNotEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-3">History</h3>
                    <ul class="space-y-2 text-sm">
                        @foreach ($calendar->reviews as $review)
                            <li>
                                <span class="text-gray-500">{{ $review->created_at->format('d M, h:i A') }}</span> —
                                <strong>{{ $review->user->name }}</strong>
                                ({{ $review->user->role->label() }}):
                                {{ ['submitted' => 'Submit kiya', 'approved' => 'Approve kiya', 'revision' => 'Revision maangi'][$review->action] ?? $review->action }}
                                @if ($review->comment)<span class="block text-gray-700 whitespace-pre-line pl-4">“{{ $review->comment }}”</span>@endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
