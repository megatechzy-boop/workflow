<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-bold text-2xl text-gray-900 tracking-tight">Leads</h2>
            <button type="button" x-data @click="navigator.clipboard.writeText(@js(route('enquiry.form'))); $el.textContent = 'Link copied'"
                class="px-4 py-2 min-h-[44px] rounded-xl border border-gray-300 text-sm font-semibold text-gray-700">Copy enquiry form link</button>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="p-3 rounded bg-green-50 text-green-800 text-sm">{{ session('status') }}</div>
            @endif

            <div class="flex flex-wrap gap-2 text-sm">
                <a href="{{ route('leads.index') }}" class="px-3 py-1.5 rounded-full {{ request('status') ? 'bg-white border border-gray-200' : 'bg-brand-600 text-white' }}">All</a>
                @foreach (App\Models\Lead::STATUSES as $value => $label)
                    <a href="{{ route('leads.index', ['status' => $value]) }}" class="px-3 py-1.5 rounded-full {{ request('status') === $value ? 'bg-brand-600 text-white' : 'bg-white border border-gray-200' }}">{{ $label }} ({{ $counts[$value] ?? 0 }})</a>
                @endforeach
            </div>

            <div class="card divide-y">
                @forelse ($leads as $lead)
                    <div class="p-4" x-data="{ open: false }">
                        <div class="flex flex-wrap items-start gap-3">
                            <div class="flex-1 min-w-[12rem]">
                                <button type="button" @click="open = !open" class="font-semibold text-left hover:text-brand-600">{{ $lead->name }}@if ($lead->business) · {{ $lead->business }}@endif</button>
                                <div class="text-xs text-gray-500">{{ implode(', ', $lead->needs) }}@if ($lead->budget) · {{ $lead->budget }}@endif @if ($lead->timeline) · {{ $lead->timeline }}@endif</div>
                                <div class="text-xs text-gray-400">{{ $lead->created_at->diffForHumans() }}</div>
                            </div>
                            <div class="flex items-center gap-2">
                                <a href="tel:{{ $lead->phoneDigits() }}" class="px-3 py-1.5 rounded-xl border border-gray-200 text-xs font-semibold text-gray-700">Call</a>
                                <a href="https://wa.me/{{ $lead->phoneDigits() }}" target="_blank" rel="noopener" class="px-3 py-1.5 rounded-xl bg-green-50 text-green-800 text-xs font-semibold">WhatsApp</a>
                                <span class="px-2 py-0.5 rounded-full text-xs {{ ['new' => 'bg-accent-50 text-accent-600', 'contacted' => 'bg-gray-100 text-gray-700', 'won' => 'bg-green-100 text-green-800', 'lost' => 'bg-red-100 text-red-700'][$lead->status] }}">{{ App\Models\Lead::STATUSES[$lead->status] }}</span>
                            </div>
                        </div>

                        <div x-show="open" x-cloak class="mt-3 grid sm:grid-cols-2 gap-4 text-sm">
                            <div class="space-y-1 text-gray-700">
                                <div><span class="text-gray-500">Phone:</span> {{ $lead->phone }}</div>
                                @if ($lead->email)<div><span class="text-gray-500">Email:</span> {{ $lead->email }}</div>@endif
                                <div><span class="text-gray-500">Goal:</span> {{ $lead->goal }}</div>
                                @if ($lead->source)<div><span class="text-gray-500">Heard from:</span> {{ $lead->source }}</div>@endif
                                @if ($lead->notes)<div><span class="text-gray-500">Notes:</span> {{ $lead->notes }}</div>@endif
                                @if ($lead->client)<div><span class="text-gray-500">Client:</span> <a href="{{ route('clients.edit', $lead->client) }}" class="text-brand-600 hover:underline">{{ $lead->client->name }}</a></div>@endif
                            </div>
                            <div class="space-y-2">
                                <form method="POST" action="{{ route('leads.update', $lead) }}" class="space-y-2">
                                    @csrf @method('PUT')
                                    <select name="status" class="w-full border-gray-300 rounded-xl text-sm" aria-label="Status">
                                        @foreach (App\Models\Lead::STATUSES as $value => $label)
                                            <option value="{{ $value }}" @selected($lead->status === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <textarea name="remarks" rows="2" placeholder="Team notes (call summary, follow-up date)" class="w-full border-gray-300 rounded-xl text-sm">{{ $lead->remarks }}</textarea>
                                    <button class="px-4 py-2 rounded-xl border border-gray-300 text-sm font-semibold text-gray-700">Save</button>
                                </form>
                                @unless ($lead->client)
                                    <form method="POST" action="{{ route('leads.convert', $lead) }}">
                                        @csrf
                                        <button class="px-4 py-2 rounded-xl bg-brand-600 text-white text-sm font-semibold">Won: make client</button>
                                    </form>
                                @endunless
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-4 py-6 text-center text-gray-500">No enquiries yet. Share the enquiry form link.</div>
                @endforelse
            </div>

            {{ $leads->links() }}
        </div>
    </div>
</x-app-layout>
