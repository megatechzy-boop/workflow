<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
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
                        <a href="{{ $url }}" class="block bg-white shadow-sm rounded-lg p-4 hover:ring-2 hover:ring-indigo-200 {{ $alert ? 'ring-1 ring-red-300' : '' }}">
                            <div class="text-3xl font-bold {{ $alert ? 'text-red-600' : 'text-gray-900' }}">{{ $value }}</div>
                            <div class="text-sm text-gray-600 mt-1">{{ $label }}</div>
                        </a>
                    @endforeach
                </div>
            @endif

            <div class="flex flex-wrap gap-3 text-sm">
                <a href="{{ route('schedule') }}" class="px-3 py-2 rounded-md bg-white shadow-sm hover:bg-gray-50">🗓 Month schedule</a>
                <a href="{{ route('calendars.index') }}" class="px-3 py-2 rounded-md bg-white shadow-sm hover:bg-gray-50">📅 Calendars</a>
                <a href="{{ route('chat.index') }}" class="px-3 py-2 rounded-md bg-white shadow-sm hover:bg-gray-50">💬 Chat</a>
                @if (auth()->user()->hasRole('client') || auth()->user()->hasPermission(App\Enums\Permission::ViewAllTasks))
                    <a href="{{ route('reports.monthly') }}" class="px-3 py-2 rounded-md bg-white shadow-sm hover:bg-gray-50">📄 Monthly report</a>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
