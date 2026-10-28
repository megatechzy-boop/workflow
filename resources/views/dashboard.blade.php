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
