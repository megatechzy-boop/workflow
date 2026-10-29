<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-bold text-2xl text-gray-900 tracking-tight">Billing</h2>
            @include('office._month')
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="p-3 rounded bg-green-50 text-green-800 text-sm">{{ session('status') }}</div>
            @endif

            <div class="grid grid-cols-3 gap-4">
                <div class="card p-4"><div class="text-sm text-gray-500">Billed</div><div class="text-xl font-bold">₹{{ number_format($invoices->sum('amount')) }}</div></div>
                <div class="card p-4"><div class="text-sm text-gray-500">Received</div><div class="text-xl font-bold text-green-700">₹{{ number_format($invoices->whereNotNull('paid_at')->sum('amount')) }}</div></div>
                <div class="card p-4"><div class="text-sm text-gray-500">Pending</div><div class="text-xl font-bold text-accent-600">₹{{ number_format($invoices->whereNull('paid_at')->sum('amount')) }}</div></div>
            </div>

            @if ($missing)
                <form method="POST" action="{{ route('admin.billing.generate', ['month' => $month->format('Y-m')]) }}" class="flex flex-wrap items-center gap-3 card p-4">
                    @csrf
                    <span class="text-sm text-gray-700 flex-1">{{ $missing }} active {{ Str::plural('client', $missing) }} with a package {{ $missing === 1 ? 'has' : 'have' }} no bill for {{ $month->format('F') }} yet.</span>
                    <button class="px-4 py-2 min-h-[44px] rounded-xl bg-brand-600 text-white text-sm font-semibold">Create bills</button>
                </form>
            @endif

            <div class="card overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-3">Client</th>
                            <th class="px-4 py-3">Package</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse ($invoices as $invoice)
                            @php($state = $invoice->state())
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ $invoice->client->name }}</td>
                                <td class="px-4 py-3">{{ $invoice->package_name }}</td>
                                <td class="px-4 py-3 text-right">₹{{ number_format($invoice->amount) }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 rounded-full text-xs {{ ['paid' => 'bg-green-100 text-green-800', 'due' => 'bg-accent-50 text-accent-600', 'overdue' => 'bg-red-100 text-red-700'][$state] }}">
                                        {{ $state === 'paid' ? 'Paid '.$invoice->paid_at->format('d M') : ($state === 'due' ? 'Due '.$invoice->due_date->format('d M') : 'Overdue') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <form method="POST" action="{{ route('admin.billing.paid', $invoice) }}">
                                        @csrf
                                        <button class="text-brand-600 hover:underline">{{ $invoice->paid_at ? 'Mark unpaid' : 'Mark paid' }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">No bills for {{ $month->format('F Y') }}.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
