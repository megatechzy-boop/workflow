<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-bold text-2xl text-gray-900 tracking-tight">Billing</h2>
            <div class="flex items-center gap-3">
                @include('office._month')
                <a href="{{ route('admin.billing.create') }}" class="inline-flex items-center px-4 py-2 bg-brand-600 rounded-xl text-sm font-semibold text-white hover:bg-brand-700 min-h-[44px]">+ New invoice</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="p-3 rounded bg-green-50 text-green-800 text-sm">{{ session('status') }}</div>
            @endif

            @php($received = $invoices->sum(fn ($i) => $i->received()))
            <div class="grid grid-cols-3 gap-4">
                <div class="card p-4"><div class="text-sm text-gray-500">Billed</div><div class="text-xl font-bold">₹{{ number_format($invoices->sum('amount')) }}</div></div>
                <div class="card p-4"><div class="text-sm text-gray-500">Received</div><div class="text-xl font-bold text-green-700">₹{{ number_format($received) }}</div></div>
                <div class="card p-4"><div class="text-sm text-gray-500">Pending</div><div class="text-xl font-bold text-accent-600">₹{{ number_format($invoices->sum('amount') - $received) }}</div></div>
            </div>

            @if ($missing)
                <form method="POST" action="{{ route('admin.billing.generate', ['month' => $month->format('Y-m')]) }}" class="flex flex-wrap items-center gap-3 card p-4">
                    @csrf
                    <span class="text-sm text-gray-700 flex-1">{{ $missing }} active {{ Str::plural('client', $missing) }} with a package {{ $missing === 1 ? 'has' : 'have' }} no monthly bill for {{ $month->format('F') }} yet.</span>
                    <button class="px-4 py-2 min-h-[44px] rounded-xl bg-brand-600 text-white text-sm font-semibold">Create monthly bills</button>
                </form>
            @endif

            <div class="card divide-y">
                @forelse ($invoices as $invoice)
                    @php($state = $invoice->state())
                    <div class="p-4" x-data="{ pay: false, edit: false }">
                        <div class="flex flex-wrap items-center gap-3">
                            <a href="{{ route('admin.billing.show', $invoice) }}" class="font-mono text-sm text-brand-600 hover:underline">{{ $invoice->number }}</a>
                            <div class="flex-1 min-w-[10rem]">
                                <div class="font-medium">{{ $invoice->client->name }}</div>
                                <div class="text-xs text-gray-500">{{ collect($invoice->items)->pluck('name')->implode(', ') }} · {{ $invoice->invoice_date->format('d M') }}</div>
                            </div>
                            <div class="text-right">
                                <div class="font-semibold">₹{{ number_format($invoice->amount) }}</div>
                                <span class="px-2 py-0.5 rounded-full text-xs {{ ['paid' => 'bg-green-100 text-green-800', 'partly' => 'bg-accent-50 text-accent-600', 'due' => 'bg-gray-100 text-gray-700', 'overdue' => 'bg-red-100 text-red-700'][$state] }}">
                                    {{ ['paid' => 'Paid', 'partly' => '₹'.number_format($invoice->due()).' due', 'due' => 'Due '.$invoice->due_date->format('d M'), 'overdue' => 'Overdue'][$state] }}
                                </span>
                            </div>
                            @unless ($invoice->paid_at)
                                <button type="button" @click="pay = !pay" class="px-3 py-1.5 rounded-xl border border-gray-300 text-xs font-semibold text-gray-700">Record payment</button>
                            @endunless
                            <a href="{{ route('admin.billing.show', $invoice) }}" class="px-3 py-1.5 rounded-xl bg-brand-50 text-brand-700 text-xs font-semibold">View invoice</a>
                            <button type="button" @click="edit = !edit" class="px-3 py-1.5 rounded-xl text-xs font-semibold text-gray-500 hover:bg-gray-100">Edit</button>
                        </div>

                        {{-- Fix a bill: change its amount (single-item bills) or delete it (no payments yet) --}}
                        <div x-show="edit" x-cloak class="mt-3 flex flex-wrap items-end gap-3">
                            @if (count($invoice->items) === 1)
                                <form method="POST" action="{{ route('admin.billing.amount', $invoice) }}" class="flex items-end gap-2">
                                    @csrf @method('PUT')
                                    <label class="text-xs text-gray-600">Bill amount (₹)
                                        <input type="number" name="rate" min="{{ max(1, $invoice->received()) }}" value="{{ $invoice->items[0]['rate'] }}" class="mt-1 block w-32 border-gray-300 rounded-xl text-sm" required>
                                    </label>
                                    <button class="px-4 py-2 rounded-xl bg-brand-600 text-white text-sm font-semibold">Save</button>
                                </form>
                            @endif
                            @if ($invoice->payments->isEmpty())
                                <form method="POST" action="{{ route('admin.billing.destroy', $invoice) }}" onsubmit="return confirm('Delete bill {{ $invoice->number }}?')">
                                    @csrf @method('DELETE')
                                    <button class="px-4 py-2 rounded-xl text-sm font-semibold text-red-600 hover:bg-red-50">Delete bill</button>
                                </form>
                            @else
                                <span class="text-xs text-gray-500">To delete this bill, remove its receipts first.</span>
                            @endif
                        </div>

                        @foreach ($invoice->payments as $payment)
                            <div class="mt-2 ms-1 text-xs text-gray-600 flex items-center gap-2">
                                <x-icon name="tick" class="w-4 h-4 text-green-600" />
                                ₹{{ number_format($payment->amount) }} · {{ $payment->method }} · {{ $payment->date->format('d M') }}
                                <a href="{{ route('admin.billing.receipt', $payment) }}" class="text-brand-600 hover:underline">Receipt {{ $payment->number }}</a>
                                <form x-show="edit" x-cloak method="POST" action="{{ route('admin.billing.receipt.destroy', $payment) }}" onsubmit="return confirm('Remove receipt {{ $payment->number }}?')">
                                    @csrf @method('DELETE')
                                    <button class="text-red-600 hover:underline">Remove</button>
                                </form>
                            </div>
                        @endforeach

                        <form x-show="pay" x-cloak method="POST" action="{{ route('admin.billing.pay', $invoice) }}" class="mt-3 grid sm:grid-cols-[8rem_9rem_10rem_1fr_auto] gap-2 items-end">
                            @csrf
                            <label class="text-xs text-gray-600">Amount (₹)
                                <input type="number" name="amount" min="1" max="{{ $invoice->due() }}" value="{{ $invoice->due() }}" class="mt-1 block w-full border-gray-300 rounded-xl text-sm" required>
                            </label>
                            <label class="text-xs text-gray-600">Date
                                <input type="date" name="date" value="{{ today()->format('Y-m-d') }}" class="mt-1 block w-full border-gray-300 rounded-xl text-sm" required>
                            </label>
                            <label class="text-xs text-gray-600">Method
                                <select name="method" class="mt-1 block w-full border-gray-300 rounded-xl text-sm">
                                    @foreach (App\Models\Payment::METHODS as $method)
                                        <option>{{ $method }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="text-xs text-gray-600">Deposited to (optional)
                                <input type="text" name="deposited_to" placeholder="Kotak Bank" class="mt-1 block w-full border-gray-300 rounded-xl text-sm">
                            </label>
                            <button class="px-4 py-2 rounded-xl bg-brand-600 text-white text-sm font-semibold">Save</button>
                        </form>
                    </div>
                @empty
                    <div class="px-4 py-6 text-center text-gray-500">No invoices for {{ $month->format('F Y') }}.</div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
