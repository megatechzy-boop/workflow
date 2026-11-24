{{-- Edit a bill that is not sent yet: lines (package amount, ad spend, extras), dates, previous balances --}}
@php($lines = collect(old('items', collect($invoice->items)->where('kind', '!=', 'carry')->values()->all())))
@php($carries = collect($invoice->items)->where('kind', 'carry')->values())
@php($rows = max(count($lines) + 2, 4))
@php($spentIn = ($invoice->month ?? $invoice->invoice_date)->copy()->subMonthNoOverflow()->format('F Y'))
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-gray-900 tracking-tight">Edit bill {{ $invoice->number }} · {{ $invoice->client->name }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('admin.billing.update', $invoice) }}" class="card p-6 space-y-5">
                @csrf @method('PUT')
                <p class="text-sm text-gray-600">Not sent yet: the customer does not see this bill. Add last month's ad spend or extras, then save or send.</p>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="invoice_date" value="Invoice date" />
                        <x-text-input id="invoice_date" name="invoice_date" type="date" class="mt-1 block w-full" :value="old('invoice_date', $invoice->invoice_date->format('Y-m-d'))" required />
                    </div>
                    <div>
                        <x-input-label for="due_date" value="Due date" />
                        <x-text-input id="due_date" name="due_date" type="date" class="mt-1 block w-full" :value="old('due_date', $invoice->due_date->format('Y-m-d'))" required />
                        <x-input-error :messages="$errors->get('due_date')" class="mt-2" />
                    </div>
                </div>

                <div class="space-y-3">
                    <div class="text-sm font-semibold text-gray-700">Items</div>
                    <div class="flex flex-wrap gap-2" id="item-chips">
                        <button type="button" data-item="Meta ads spend" data-detail="{{ $spentIn }}" data-kind="ads" class="px-3 py-1 rounded-full border border-brand-300 text-xs text-brand-700 hover:bg-brand-50">+ Meta ads spend ({{ $spentIn }})</button>
                        @foreach (['Extra reel', 'Extra post', 'Ad management'] as $chip)
                            <button type="button" data-item="{{ $chip }}" class="px-3 py-1 rounded-full border border-gray-300 text-xs text-gray-700 hover:bg-gray-50">+ {{ $chip }}</button>
                        @endforeach
                    </div>
                    <p class="text-xs text-gray-500">Empty a line's name or rate to take it out.</p>
                    @for ($i = 0; $i < $rows; $i++)
                        @php($line = $lines[$i] ?? [])
                        <div class="grid grid-cols-[minmax(0,1fr)_4rem_6.5rem] sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_5rem_8rem] gap-2">
                            <input type="hidden" name="items[{{ $i }}][kind]" value="{{ $line['kind'] ?? '' }}">
                            <input type="text" name="items[{{ $i }}][name]" value="{{ $line['name'] ?? '' }}" placeholder="Item" class="min-w-0 w-full border-gray-300 rounded-xl text-sm" aria-label="Item {{ $i + 1 }} name">
                            <input type="text" name="items[{{ $i }}][detail]" value="{{ $line['detail'] ?? '' }}" placeholder="Detail (optional)" class="hidden sm:block min-w-0 w-full border-gray-300 rounded-xl text-sm" aria-label="Item {{ $i + 1 }} detail">
                            <input type="number" name="items[{{ $i }}][qty]" value="{{ $line['qty'] ?? 1 }}" min="1" class="min-w-0 w-full border-gray-300 rounded-xl text-sm" aria-label="Item {{ $i + 1 }} quantity">
                            <input type="number" name="items[{{ $i }}][rate]" value="{{ $line['rate'] ?? '' }}" min="0" placeholder="Rate ₹" class="min-w-0 w-full border-gray-300 rounded-xl text-sm" aria-label="Item {{ $i + 1 }} rate">
                        </div>
                    @endfor
                    <x-input-error :messages="$errors->get('items')" class="mt-2" />

                    @if ($carries->isNotEmpty())
                        <div class="rounded-xl border border-gray-200 p-3 space-y-2">
                            <div class="text-sm font-semibold text-gray-700">Previous balance</div>
                            @foreach ($carries as $carry)
                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" name="carry[]" value="{{ $carry['from'] }}" @checked(in_array($carry['from'], old('carry', $carries->pluck('from')->all()))) class="rounded border-gray-300 text-brand-600">
                                    <span class="flex-1">{{ $carry['detail'] }}</span>
                                    <span class="font-semibold">₹{{ number_format($carry['rate']) }}</span>
                                </label>
                            @endforeach
                            <p class="text-xs text-gray-500">Untick to keep that amount on its old bill instead.</p>
                        </div>
                    @endif
                    @if ($invoice->gst_rate > 0)
                        <p class="text-xs text-gray-500">GST {{ $invoice->gst_rate + 0 }}% is added on the items (not on a previous balance).</p>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <x-primary-button>Save</x-primary-button>
                    <button name="send" value="1" class="px-4 py-2 min-h-[44px] rounded-xl bg-brand-600 text-white text-sm font-semibold hover:bg-brand-700"
                        data-confirm="Save and send {{ $invoice->number }} to {{ $invoice->client->name }}? They get a message and an email.">Save &amp; send</button>
                    <a href="{{ route('admin.billing.index', ['month' => $invoice->invoice_date->format('Y-m')]) }}" class="text-sm text-gray-600 hover:underline">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        (() => {
            const field = (i, key) => document.querySelector(`[name="items[${i}][${key}]"]`);
            const rows = {{ $rows }};
            document.querySelectorAll('#item-chips [data-item]').forEach((chip) => chip.addEventListener('click', () => {
                const i = [...Array(rows).keys()].find((n) => !field(n, 'name').value);
                if (i === undefined) return;
                field(i, 'name').value = chip.dataset.item;
                field(i, 'detail').value = chip.dataset.detail || '';
                field(i, 'kind').value = chip.dataset.kind || '';
                field(i, 'rate').focus();
            }));
        })();
    </script>
</x-app-layout>
