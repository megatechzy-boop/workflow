<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-gray-900 tracking-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <p class="text-gray-700">
                Welcome, <strong>{{ auth()->user()->name }}</strong>! Your role: <strong>{{ auth()->user()->role->label() }}</strong>
            </p>

            @if (session('status'))
                <div class="p-3 rounded bg-green-50 text-green-800 text-sm">{{ session('status') }}</div>
            @endif

            @if ($attendance)
                @php($today = $attendance['today'])
                <div class="card p-4 max-w-md">
                    <div class="flex items-center justify-between">
                        <div class="font-semibold text-gray-900">{{ now()->format('D, d M') }}</div>
                        <div class="text-xs text-gray-500">
                            {{ now()->format('F') }}: {{ $attendance['month']['present'] ?? 0 }} present · {{ $attendance['month']['half_day'] ?? 0 }} half day · {{ $attendance['month']['leave'] ?? 0 }} leave
                        </div>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-2 text-center">
                        <div class="rounded-xl px-3 py-2 {{ $today?->check_in_at ? 'bg-brand-50 text-brand-700' : 'bg-gray-50 text-gray-500' }}">
                            <div class="text-xs">Check in</div><div class="font-bold">{{ $today?->check_in_at?->format('h:i A') ?? '--:--' }}</div>
                        </div>
                        <div class="rounded-xl px-3 py-2 {{ $today?->check_out_at ? 'bg-brand-50 text-brand-700' : 'bg-gray-50 text-gray-500' }}">
                            <div class="text-xs">Check out</div><div class="font-bold">{{ $today?->check_out_at?->format('h:i A') ?? '--:--' }}</div>
                        </div>
                    </div>
                    @if (! $today?->check_in_at)
                        <form method="POST" action="{{ route('attendance.checkIn') }}" class="mt-3">
                            @csrf
                            <button class="w-full py-2.5 rounded-xl bg-brand-600 text-white text-sm font-semibold">Check in</button>
                        </form>
                    @elseif (! $today->check_out_at)
                        <form method="POST" action="{{ route('attendance.checkOut') }}" class="mt-3">
                            @csrf
                            <button class="w-full py-2.5 rounded-xl border border-gray-300 text-sm font-semibold text-gray-700">Check out</button>
                        </form>
                    @endif
                </div>
            @endif

            @if ($myBills->isNotEmpty())
                @php($pending = $myBills->sum(fn ($b) => $b->due()))
                <div class="card p-5 max-w-md">
                    <div class="flex items-baseline justify-between gap-3">
                        <div class="font-semibold text-gray-900">Your bills</div>
                        @if ($pending > 0)
                            <div class="text-sm font-semibold text-accent-600">₹{{ number_format($pending) }} pending</div>
                        @else
                            <div class="text-sm text-green-700">All paid</div>
                        @endif
                    </div>
                    <div class="mt-3 divide-y divide-gray-100">
                        @foreach ($myBills as $bill)
                            @php($state = $bill->state())
                            <div class="py-2.5">
                                <div class="flex items-center justify-between gap-3 text-sm">
                                    <a href="{{ route('my.invoice', $bill) }}" class="min-w-0 hover:text-brand-600">
                                        <span class="font-medium">{{ $bill->number }}</span>
                                        <span class="text-gray-500">· {{ ($bill->month ?? $bill->invoice_date)->format('M Y') }}</span>
                                    </a>
                                    <span class="whitespace-nowrap">
                                        ₹{{ number_format($bill->amount) }}
                                        <span class="ms-1 px-2 py-0.5 rounded-full text-xs {{ ['paid' => 'bg-green-100 text-green-800', 'partly' => 'bg-accent-50 text-accent-600', 'due' => 'bg-gray-100 text-gray-700', 'overdue' => 'bg-red-100 text-red-700'][$state] }}">
                                            {{ ['paid' => 'Paid', 'partly' => '₹'.number_format($bill->due()).' due', 'due' => 'Due '.$bill->due_date->format('d M'), 'overdue' => 'Overdue'][$state] }}
                                        </span>
                                    </span>
                                </div>
                                @foreach ($bill->payments as $payment)
                                    <a href="{{ route('my.receipt', $payment) }}" class="block text-xs text-brand-600 hover:underline mt-0.5">Receipt {{ $payment->number }} · ₹{{ number_format($payment->amount) }} · {{ $payment->date->format('d M') }}</a>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($myPackage)
                @php($package = $myPackage['package'])
                <div class="card p-5 max-w-md">
                    <div class="text-sm text-gray-500">Your package</div>
                    <div class="flex items-baseline justify-between gap-3">
                        <div class="text-xl font-bold text-gray-900">{{ $package->name }}</div>
                        <div class="font-semibold">{{ $package->priceLabel() }}<span class="text-sm font-normal text-gray-500"> / month</span></div>
                    </div>
                    @if ($myPackage['since'])
                        <div class="text-xs text-gray-500">Active since {{ $myPackage['since']->format('d M Y') }}</div>
                    @endif
                    <div class="mt-4 text-sm font-semibold text-gray-900">Published this month</div>
                    <div class="mt-2 grid grid-cols-3 gap-2">
                        @foreach (App\Enums\PostType::cases() as $type)
                            <div class="rounded-xl bg-gray-50 px-3 py-2 text-center">
                                <div class="text-xs text-gray-500">{{ $type === App\Enums\PostType::Post ? 'Static' : $type->label() }}</div>
                                <div class="font-bold">{{ $myPackage['published'][$type->value] ?? 0 }}/{{ $package->quota($type) }}</div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-4 text-sm font-semibold text-gray-900">Included</div>
                    <ul class="mt-2 space-y-1 text-sm text-gray-700">
                        <li class="flex gap-2"><x-icon name="tick" class="w-4 h-4 mt-0.5 text-brand-600 shrink-0" />{{ $package->platformLabel() }}</li>
                        @foreach ($package->services as $service)
                            <li class="flex gap-2"><x-icon name="tick" class="w-4 h-4 mt-0.5 text-brand-600 shrink-0" />{{ $service }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- 1. Today's work: what needs this person now --}}
            @if ($todo !== [] || ! auth()->user()->hasRole('client'))
                <section class="card overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="font-semibold text-gray-900">Today's work</h3>
                        <span class="text-xs text-gray-500">{{ now()->format('D, d M') }}</span>
                    </div>
                    @forelse ($todo as [$brand, $detail, $badge, $tone, $url])
                        <a href="{{ $url }}" class="flex items-center gap-3 px-4 py-3 border-b border-gray-100 last:border-0 hover:bg-gray-50">
                            <div class="flex-1 min-w-0">
                                <div class="font-medium text-gray-900 truncate">{{ $brand }}</div>
                                <div class="text-sm text-gray-500 truncate">{{ $detail }}</div>
                            </div>
                            <span class="shrink-0 px-2 py-1 rounded-full text-xs font-semibold {{ $tone }}">{{ $badge }}</span>
                            <x-icon name="chevron-right" class="w-4 h-4 text-gray-400 shrink-0" />
                        </a>
                    @empty
                        <p class="px-4 py-6 text-center text-sm text-gray-500">Nothing due today or tomorrow.</p>
                    @endforelse
                </section>
            @endif

            @if ($cards)
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    @foreach ($cards as $card)
                        @php([$label, $value, $url] = $card)
                        @php($alert = ($card[3] ?? null) === 'red' && $value > 0)
                        <a href="{{ $url }}" class="block bg-white shadow-sm rounded-lg p-4 hover:ring-2 hover:ring-brand-200 {{ $alert ? 'ring-1 ring-red-300' : '' }}">
                            <div class="text-3xl font-bold {{ $alert ? 'text-red-600' : 'text-gray-900' }}">{{ $value }}</div>
                            <div class="text-sm text-gray-600 mt-1">{{ $label }}</div>
                        </a>
                    @endforeach
                </div>
            @endif

            {{-- 2. This week: posts per day, coloured by live status --}}
            @if ($week)
                <section class="card overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="font-semibold text-gray-900">This week</h3>
                        <a href="{{ route('schedule') }}" class="text-sm text-brand-600 hover:underline">Full schedule</a>
                    </div>
                    <div class="overflow-x-auto">
                        <div class="grid grid-cols-7 min-w-[700px] divide-x divide-gray-100">
                            @foreach ($week as ['day' => $day, 'items' => $items])
                                <div class="p-2 min-h-[120px] {{ $day->isToday() ? 'bg-brand-50' : '' }}">
                                    <div class="text-xs font-semibold {{ $day->isToday() ? 'text-brand-700' : 'text-gray-500' }}">{{ $day->format('D d') }}</div>
                                    <div class="mt-2 space-y-1">
                                        @foreach ($items as $item)
                                            @php([$label, $color] = App\Http\Controllers\ScheduleController::status($item))
                                            <a href="{{ route('calendars.show', $item->calendar) }}" title="{{ $label }}" class="block rounded-md px-1.5 py-1 text-[11px] leading-tight {{ $color }}">
                                                <span class="font-semibold block truncate">{{ $item->calendar->client->name }}</span>
                                                <span class="block truncate">{{ collect($item->formats())->map->label()->implode(', ') }} · {{ $label }}</span>
                                            </a>
                                        @endforeach
                                        @if ($items->isEmpty())
                                            <div class="text-[11px] text-gray-400">—</div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>
            @endif

            {{-- 3. Brands this month: live vs planned (and the package quota) --}}
            @if ($brands)
                <section class="card overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="font-semibold text-gray-900">Brands · {{ now()->format('F') }}</h3>
                        <span class="flex items-center gap-3 text-xs text-gray-500">
                            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-brand-600"></span>Live</span>
                            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-green-400"></span>Ready</span>
                        </span>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @foreach ($brands as $b)
                            @php($total = max($b['planned'], 1))
                            <a href="{{ $b['url'] }}" class="block px-4 py-3 hover:bg-gray-50">
                                <div class="flex items-baseline justify-between gap-3">
                                    <span class="font-medium text-gray-900 truncate">{{ $b['name'] }}</span>
                                    <span class="text-sm text-gray-600 tabular-nums shrink-0">
                                        <strong class="text-gray-900">{{ $b['live'] }}</strong> / {{ $b['planned'] }} live
                                        @if ($b['quota'])<span class="text-gray-400"> · package {{ $b['quota'] }}</span>@endif
                                    </span>
                                </div>
                                <div class="mt-2 flex h-2 rounded-full overflow-hidden bg-gray-100">
                                    <div class="bg-brand-600" style="width: {{ $b['live'] / $total * 100 }}%"></div>
                                    <div class="bg-green-400" style="width: {{ $b['ready'] / $total * 100 }}%"></div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
