<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-bold text-2xl text-gray-900 tracking-tight flex items-center gap-2"><x-icon name="grid" class="w-7 h-7 text-brand-600" /> Schedule · {{ $month->format('F Y') }}</h2>
            <form method="GET" class="flex flex-wrap items-center gap-2">
                <a href="{{ route('schedule', ['month' => $month->copy()->subMonth()->format('Y-m'), 'client' => $clientId]) }}" class="px-2 py-1 rounded border text-sm" aria-label="Previous month"><x-icon name="chevron-left" class="w-4 h-4" /></a>
                <input type="month" name="month" value="{{ $month->format('Y-m') }}" class="border-gray-300 rounded-xl shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm" aria-label="Month">
                <a href="{{ route('schedule', ['month' => $month->copy()->addMonth()->format('Y-m'), 'client' => $clientId]) }}" class="px-2 py-1 rounded border text-sm" aria-label="Next month"><x-icon name="chevron-right" class="w-4 h-4" /></a>
                @if ($brands->isNotEmpty())
                    <select name="client" class="border-gray-300 rounded-xl shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm" aria-label="Brand">
                        <option value="">All brands</option>
                        @foreach ($brands as $b)
                            <option value="{{ $b->id }}" @selected($clientId === $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                @endif
                <x-secondary-button type="submit">Show</x-secondary-button>
            </form>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="card overflow-x-auto">
                <div class="grid grid-cols-7 min-w-[840px] text-xs">
                    @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d)
                        <div class="px-2 py-2 font-semibold text-gray-600 bg-gray-50 border-b">{{ $d }}</div>
                    @endforeach

                    {{-- Empty cells before the 1st (week starts Monday) --}}
                    @for ($i = 1; $i < $month->dayOfWeekIso; $i++)
                        <div class="border-b border-r min-h-[110px] bg-gray-50/50"></div>
                    @endfor

                    @for ($day = 1; $day <= $month->daysInMonth; $day++)
                        @php($date = $month->copy()->day($day))
                        <div class="border-b border-r min-h-[110px] p-1.5 {{ $date->isToday() ? 'bg-brand-50' : '' }}">
                            <div class="font-semibold {{ $date->isToday() ? 'text-brand-700' : 'text-gray-500' }}">{{ $day }}</div>
                            <div class="space-y-1 mt-1">
                                @foreach ($byDay[$day] ?? [] as $item)
                                    @php([$label, $color] = App\Http\Controllers\ScheduleController::status($item))
                                    @php($link = $item->task && ! auth()->user()->hasRole('client') ? route('tasks.show', $item->task) : route('calendars.show', $item->calendar))
                                    <a href="{{ $link }}" class="block rounded px-1.5 py-1 {{ $color }} hover:ring-1 hover:ring-gray-400" title="{{ $item->typeLabel() }}">
                                        @unless ($clientId)<span class="font-semibold">{{ $item->calendar->client->name }}</span> · @endunless
                                        {{ collect($item->formats())->map->label()->implode(', ') }}
                                        <span class="block opacity-80">{{ $label }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endfor
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
