<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-gray-900 tracking-tight">Meta leads</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="p-3 rounded bg-green-50 text-green-800 text-sm">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="p-3 rounded bg-red-50 text-red-700 text-sm">{{ $errors->first() }}</div>
            @endif

            <form method="GET" class="flex flex-wrap gap-2">
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Search name, phone, email"
                    class="border-gray-300 rounded-xl shadow-sm text-sm min-h-[44px] flex-1 min-w-[12rem]" aria-label="Search">
                @unless ($isCustomer)
                    <select name="client" onchange="this.form.submit()" class="border-gray-300 rounded-xl shadow-sm text-sm min-h-[44px]" aria-label="Client">
                        <option value="">All clients</option>
                        @foreach ($clients as $id => $name)
                            <option value="{{ $id }}" @selected(request('client') == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                @endunless
                <select name="status" onchange="this.form.submit()" class="border-gray-300 rounded-xl shadow-sm text-sm min-h-[44px]" aria-label="Status">
                    <option value="">Any status</option>
                    @foreach (App\Models\MetaLead::STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="followup" onchange="this.form.submit()" class="border-gray-300 rounded-xl shadow-sm text-sm min-h-[44px]" aria-label="Follow-up">
                    <option value="">All leads</option>
                    <option value="today" @selected(request('followup') === 'today')>Follow-up today ({{ $dueCount }})</option>
                </select>
                <button class="px-3 min-h-[44px] border rounded-xl text-sm inline-flex items-center gap-1"><x-icon name="search" class="w-4 h-4" /> Search</button>
                <a href="{{ route('meta.leads.export', request()->query()) }}" class="px-3 min-h-[44px] border rounded-xl text-sm inline-flex items-center gap-1">
                    <x-icon name="download" class="w-4 h-4" /> Export CSV</a>
            </form>

            @forelse ($leads->groupBy(fn ($l) => $l->client->name ?? $l->page->name) as $group => $rows)
                <div class="card">
                    @unless ($isCustomer)
                        <div class="px-4 py-2 border-b font-semibold text-gray-900">{{ $group }}</div>
                    @endunless
                    <div class="divide-y">
                        @foreach ($rows as $lead)
                            @php($last = $lead->remarks->first())
                            <div class="p-4 space-y-2" x-data="{ more: false, history: false, note: false, text: '' }">
                                <div class="flex flex-wrap items-start gap-3">
                                    <div class="flex-1 min-w-[14rem]">
                                        <div class="font-semibold flex flex-wrap items-center gap-2">
                                            {{ $lead->name ?? 'No name' }}
                                            @if ($lead->is_repeat)
                                                <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">Repeat</span>
                                            @endif
                                            @if ($lead->followUpDue())
                                                <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-red-100 text-red-700">
                                                    Follow-up {{ $lead->follow_up_on->isToday() ? 'today' : $lead->follow_up_on->format('d M').' (missed)' }}</span>
                                            @elseif ($lead->follow_up_on && ! in_array($lead->status, ['won', 'lost']))
                                                <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-gray-100 text-gray-700">Follow-up {{ $lead->follow_up_on->format('d M') }}</span>
                                            @endif
                                        </div>
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
                                        <select name="status" onchange="this.form.submit()" class="border-gray-300 rounded-xl shadow-sm text-sm min-h-[44px]" aria-label="Status">
                                            @foreach (App\Models\MetaLead::STATUSES as $value => $label)
                                                <option value="{{ $value }}" @selected($lead->status === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                </div>

                                @if ($last)
                                    <div class="text-sm bg-gray-50 rounded-xl px-3 py-2">
                                        <span class="text-xs text-gray-500">{{ $last->user->name ?? 'Someone' }} · {{ $last->created_at->format('d M, h:i a') }}:</span>
                                        {{ $last->remark }}
                                        @if ($lead->remarks->count() > 1)
                                            · <button type="button" class="text-xs text-brand-600 hover:underline" @click="history = !history">history ({{ $lead->remarks->count() }})</button>
                                        @endif
                                    </div>
                                    <ul x-show="history" x-cloak class="text-sm space-y-1 pl-3 border-l-2 border-gray-200">
                                        @foreach ($lead->remarks->skip(1) as $remark)
                                            <li><span class="text-xs text-gray-500">{{ $remark->user->name ?? 'Someone' }} · {{ $remark->created_at->format('d M, h:i a') }}:</span> {{ $remark->remark }}
                                                @if ($remark->follow_up_on)<span class="text-xs text-gray-500">(follow-up {{ $remark->follow_up_on->format('d M') }})</span>@endif</li>
                                        @endforeach
                                    </ul>
                                @endif

                                <button type="button" x-show="!note" class="text-sm text-brand-600 hover:underline" @click="note = true">+ Add remark</button>
                                <form x-show="note" x-cloak method="POST" action="{{ route('meta.leads.remark', $lead) }}" class="space-y-2">
                                    @csrf
                                    <div class="flex flex-wrap gap-2">
                                        @foreach (App\Models\MetaLeadRemark::QUICK as $quick)
                                            <button type="button" class="text-xs px-3 py-1.5 rounded-full border hover:bg-gray-50" @click="text = @js($quick)">{{ $quick }}</button>
                                        @endforeach
                                    </div>
                                    <textarea name="remark" x-model="text" rows="2" required maxlength="1000" placeholder="What happened? e.g. Called, will visit on Monday"
                                        class="w-full border-gray-300 rounded-xl shadow-sm text-sm"></textarea>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <label class="text-sm text-gray-600">Next follow-up
                                            <input type="date" name="follow_up_on" min="{{ today()->toDateString() }}" class="ml-1 border-gray-300 rounded-xl shadow-sm text-sm min-h-[44px]">
                                        </label>
                                        <button class="px-4 min-h-[44px] rounded-xl bg-brand-600 text-white text-sm font-semibold">Save remark</button>
                                        <button type="button" class="text-sm text-gray-500" @click="note = false">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="card px-4 py-6 text-center text-gray-500">
                    @if (request()->anyFilled(['q', 'status', 'client', 'followup']))
                        No leads match these filters.
                    @else
                        No leads yet. Leads from Facebook / Instagram lead forms come in every 10 minutes.
                    @endif
                </div>
            @endforelse

            {{ $leads->links() }}
        </div>
    </div>
</x-app-layout>
