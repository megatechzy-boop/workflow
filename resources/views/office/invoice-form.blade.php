<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-gray-900 tracking-tight">New invoice</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('admin.billing.store') }}" class="card p-6 space-y-5">
                @csrf
                <div class="grid sm:grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="client_id" value="Client" />
                        <select id="client_id" name="client_id" class="mt-1 block w-full border-gray-300 rounded-xl shadow-sm" required>
                            <option value="">Choose…</option>
                            @foreach ($clients as $client)
                                <option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>{{ $client->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('client_id')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="invoice_date" value="Invoice date" />
                        <x-text-input id="invoice_date" name="invoice_date" type="date" class="mt-1 block w-full" :value="old('invoice_date', today()->format('Y-m-d'))" required />
                    </div>
                    <div>
                        <x-input-label for="due_date" value="Due date" />
                        <x-text-input id="due_date" name="due_date" type="date" class="mt-1 block w-full" :value="old('due_date', today()->addDays(config('business.payment_days'))->format('Y-m-d'))" required />
                        <x-input-error :messages="$errors->get('due_date')" class="mt-2" />
                    </div>
                </div>

                <div class="space-y-3">
                    <div class="text-sm font-semibold text-gray-700">Items</div>
                    @for ($i = 0; $i < 4; $i++)
                        <div class="grid grid-cols-[minmax(0,1fr)_4rem_6.5rem] sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_5rem_8rem] gap-2">
                            <input type="text" name="items[{{ $i }}][name]" value="{{ old("items.$i.name") }}" placeholder="{{ $i === 0 ? 'Website update yearly' : 'Item' }}" class="min-w-0 w-full border-gray-300 rounded-xl text-sm" aria-label="Item {{ $i + 1 }} name">
                            <input type="text" name="items[{{ $i }}][detail]" value="{{ old("items.$i.detail") }}" placeholder="{{ $i === 0 ? '01/09/2026 to 31/08/2027' : 'Detail (optional)' }}" class="hidden sm:block min-w-0 w-full border-gray-300 rounded-xl text-sm" aria-label="Item {{ $i + 1 }} detail">
                            <input type="number" name="items[{{ $i }}][qty]" value="{{ old("items.$i.qty", 1) }}" min="1" class="min-w-0 w-full border-gray-300 rounded-xl text-sm" aria-label="Item {{ $i + 1 }} quantity">
                            <input type="number" name="items[{{ $i }}][rate]" value="{{ old("items.$i.rate") }}" min="0" placeholder="Rate ₹" class="min-w-0 w-full border-gray-300 rounded-xl text-sm" aria-label="Item {{ $i + 1 }} rate">
                        </div>
                    @endfor
                    <x-input-error :messages="$errors->get('items')" class="mt-2" />
                    @if (config('business.gst_rate') > 0)
                        <p class="text-xs text-gray-500">GST {{ config('business.gst_rate') }}% will be added.</p>
                    @endif
                </div>

                <div class="flex items-center gap-3">
                    <x-primary-button>Create invoice</x-primary-button>
                    <a href="{{ route('admin.billing.index') }}" class="text-sm text-gray-600 hover:underline">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
