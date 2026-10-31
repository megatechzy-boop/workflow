<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-gray-900 tracking-tight">Meta leads</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <form method="GET" class="flex flex-wrap gap-2">
                @unless ($isCustomer)
                    <select name="client" onchange="this.form.submit()" class="border-gray-300 rounded-xl shadow-sm text-sm">
                        <option value="">All clients</option>
                        @foreach ($clients as $id => $name)
                            <option value="{{ $id }}" @selected(request('client') == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                @endunless
                <select name="status" onchange="this.form.submit()" class="border-gray-300 rounded-xl shadow-sm text-sm">
                    <option value="">Any status</option>
                    @foreach (App\Models\MetaLead::STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>

            @forelse ($leads->groupBy(fn ($l) => $l->client->name ?? $l->page->name) as $group => $rows)
                <div class="card">
                    @unless ($isCustomer)
                        <div class="px-4 py-2 border-b font-semibold text-gray-900">{{ $group }}</div>
                    @endunless
                    <div class="divide-y">
                        @foreach ($rows as $lead)
                            <div class="p-4 flex flex-wrap items-start gap-3" x-data="{ more: false }">
                                <div class="flex-1 min-w-[14rem]">
                                    <div class="font-semibold">{{ $lead->name ?? 'No name' }}</div>
                                    <div class="text-sm text-gray-700">
                                        @if ($lead->phone)
                                            <a href="tel:+{{ $lead->phoneDigits() }}" class="text-brand-600 hover:underline">{{ $lead->phone }}</a>
                                            · <a href="https://wa.me/{{ $lead->phoneDigits() }}" target="_blank" rel="noopener" class="text-brand-600 hover:underline">WhatsApp</a>
                                        @endif
                                        @if ($lead->email) · {{ $lead->email }} @endif
                                    </div>
                                    <div class="text-xs text-gray-500">{{ $lead->form_name }} · {{ $lead->lead_at?->format('d M, h:i a') }}
                                        · <button type="button" class="text-brand-600 hover:underline" @click="more = !more">answers</button></div>
                                    <dl x-show="more" x-cloak class="mt-2 text-xs grid grid-cols-[auto,1fr] gap-x-3 gap-y-1">
                                        @foreach ($lead->fields as $question => $answer)
                                            <dt class="text-gray-500">{{ str_replace('_', ' ', $question) }}</dt><dd>{{ $answer }}</dd>
                                        @endforeach
                                    </dl>
                                </div>
                                <form method="POST" action="{{ route('meta.leads.update', $lead) }}">
                                    @csrf @method('PUT')
                                    <select name="status" onchange="this.form.submit()" class="border-gray-300 rounded-xl shadow-sm text-sm">
                                        @foreach (App\Models\MetaLead::STATUSES as $value => $label)
                                            <option value="{{ $value }}" @selected($lead->status === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="card px-4 py-6 text-center text-gray-500">No leads yet. Leads from Facebook / Instagram lead forms come in every 10 minutes.</div>
            @endforelse

            {{ $leads->links() }}
        </div>
    </div>
</x-app-layout>
