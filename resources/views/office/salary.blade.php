<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-bold text-2xl text-gray-900 tracking-tight">Salary</h2>
            @include('office._month')
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="p-3 rounded bg-green-50 text-green-800 text-sm">{{ session('status') }}</div>
            @endif

            <p class="text-sm text-gray-600">Pay = monthly salary × paid days ÷ working days. Present = 1 day, half day = ½, leave or no check-in = 0.</p>

            <div class="card overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Monthly salary (₹)</th>
                            <th class="px-4 py-3 text-center">Days</th>
                            <th class="px-4 py-3 text-right">Pay</th>
                            <th class="px-4 py-3 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($rows as $row)
                            @php($user = $row['user'])
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ $user->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $user->role->label() }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <form method="POST" action="{{ route('admin.salary.set', $user) }}" class="flex gap-2">
                                        @csrf @method('PUT')
                                        <input type="number" name="monthly_salary" min="0" value="{{ $user->monthly_salary }}" class="w-28 border-gray-300 rounded-xl text-sm py-1" aria-label="Monthly salary for {{ $user->name }}">
                                        <button class="text-brand-600 hover:underline">Save</button>
                                    </form>
                                </td>
                                <td class="px-4 py-3 text-center">{{ $row['days'] + 0 }}/{{ $row['working'] }}</td>
                                <td class="px-4 py-3 text-right font-semibold">₹{{ number_format($row['payment']?->amount ?? $row['payable']) }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    @if ($row['payment'])
                                        <span class="text-green-700">Paid {{ $row['payment']->paid_at->format('d M') }}</span>
                                        <form method="POST" action="{{ route('admin.salary.unpay', [$user, 'month' => $month->format('Y-m')]) }}" class="inline" onsubmit="return confirm('Remove this payment?')">
                                            @csrf @method('DELETE')
                                            <button class="ms-2 text-xs text-gray-500 hover:underline">Undo</button>
                                        </form>
                                    @elseif ($row['payable'] > 0)
                                        <form method="POST" action="{{ route('admin.salary.pay', [$user, 'month' => $month->format('Y-m')]) }}">
                                            @csrf
                                            <button class="px-3 py-1.5 rounded-xl bg-brand-600 text-white text-xs font-semibold">Mark paid</button>
                                        </form>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
