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
        </div>
    </div>
</x-app-layout>
