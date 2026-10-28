<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-bold text-2xl text-gray-900 tracking-tight flex items-center gap-2"><x-icon name="trophy" class="w-7 h-7 text-accent-500" /> Leaderboard · {{ $month->format('F Y') }}</h2>
            <form method="GET" class="flex gap-2">
                <input type="month" name="month" value="{{ $month->format('Y-m') }}" class="border-gray-300 rounded-xl shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm">
                <x-secondary-button type="submit">View</x-secondary-button>
            </form>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 grid md:grid-cols-2 gap-6">
            <div class="card overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr><th class="px-4 py-3 w-12">#</th><th class="px-4 py-3">Member</th><th class="px-4 py-3 text-right">Points</th></tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse ($board as $row)
                            <tr class="{{ $row->user->id === auth()->id() ? 'bg-brand-50' : '' }}">
                                <td class="px-4 py-3 font-semibold">@if ($loop->iteration <= 3)<x-icon name="trophy" class="w-5 h-5 {{ [1 => 'text-accent-500', 2 => 'text-gray-400', 3 => 'text-orange-700'][$loop->iteration] }}" /><span class="sr-only">{{ $loop->iteration }}</span>@else{{ $loop->iteration }}@endif</td>
                                <td class="px-4 py-3">
                                    @if (auth()->user()->hasPermission(App\Enums\Permission::ViewAllTasks))
                                        <a href="{{ route('leaderboard', ['month' => $month->format('Y-m'), 'user' => $row->user->id]) }}" class="hover:underline">{{ $row->user->name }}</a>
                                    @else
                                        {{ $row->user->name }}
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-semibold {{ $row->total < 0 ? 'text-red-600' : '' }}">{{ $row->total }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-6 text-center text-gray-500">No members.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="card p-4">
                <h3 class="font-semibold text-gray-800 mb-3">{{ $historyUser->id === auth()->id() ? 'My points' : $historyUser->name.'s points' }} ({{ $history->sum('points') }})</h3>
                <ul class="divide-y text-sm">
                    @forelse ($history as $entry)
                        @php($item = $entry->task?->item ?? $entry->content?->task?->item)
                        <li class="py-2 flex items-center justify-between gap-3">
                            <div>
                                <div class="text-gray-800">{{ $entry->label() }}{{ $entry->content ? ' · '.$entry->content->typeLabel() : '' }}</div>
                                <div class="text-xs text-gray-500">
                                    {{ $item ? $item->calendar->client->name.' · post '.$item->post_date->format('d M') : '' }} · {{ $entry->created_at->format('d M') }}
                                </div>
                            </div>
                            <span class="font-semibold {{ $entry->points < 0 ? 'text-red-600' : 'text-green-700' }}">{{ $entry->points > 0 ? '+' : '' }}{{ $entry->points }}</span>
                        </li>
                    @empty
                        <li class="py-4 text-gray-500">No points this month yet.</li>
                    @endforelse
                </ul>
                <p class="text-xs text-gray-400 mt-4">
                    On-time submit {{ config('points.on_time') > 0 ? '+' : '' }}{{ config('points.on_time') }} ·
                    First-time approval +{{ config('points.first_approve') }} ·
                    Late {{ config('points.late') }}
                </p>
            </div>
        </div>
    </div>
</x-app-layout>
