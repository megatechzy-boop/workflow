<x-report-sheet title="Leads report" kicker="Leads report" slug="leads" :brands="$brands" :client="$client" :month="$month">
    <section class="grid grid-cols-5 gap-3">
        <div class="rounded-xl bg-gray-900 text-white p-4">
            <div class="text-3xl font-bold tabular-nums">{{ $leads->count() }}</div>
            <div class="text-xs text-white/70 mt-1">Leads</div>
        </div>
        @foreach (App\Models\MetaLead::STATUSES as $key => $label)
            <div class="rounded-xl border border-gray-200 p-4">
                <div class="text-3xl font-bold text-gray-900 tabular-nums">{{ $byStatus[$key] }}</div>
                <div class="text-xs text-gray-600 mt-1">{{ $label }}</div>
            </div>
        @endforeach
    </section>

    @if ($byForm->isNotEmpty())
        <section>
            <h3 class="text-sm font-bold uppercase tracking-[0.1em] text-gray-500 mb-3">By form</h3>
            <div class="space-y-2">
                @foreach ($byForm as $form => $count)
                    <div class="flex items-center gap-3 text-sm">
                        <span class="w-48 truncate">{{ $form }}</span>
                        <span class="flex-1 h-2.5 rounded-full bg-gray-100 overflow-hidden"><span class="block h-full bg-brand-600" style="width: {{ $count / $byForm->max() * 100 }}%"></span></span>
                        <span class="w-10 text-right tabular-nums font-semibold">{{ $count }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section>
        <h3 class="text-sm font-bold uppercase tracking-[0.1em] text-gray-500 mb-3">Leads</h3>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs uppercase tracking-wider text-gray-500 border-b-2 border-gray-200">
                    <th class="py-2 pr-3 w-24">Date</th>
                    <th class="py-2 pr-3">Name</th>
                    <th class="py-2 pr-3">Phone</th>
                    <th class="py-2 pr-3">Form</th>
                    <th class="py-2 text-right">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($leads as $lead)
                    <tr class="border-b border-gray-100">
                        <td class="py-2 pr-3 whitespace-nowrap">{{ $lead->lead_at?->format('d M') }}</td>
                        <td class="py-2 pr-3">{{ $lead->name ?? '—' }}</td>
                        <td class="py-2 pr-3 whitespace-nowrap">{{ $lead->phone ?? '—' }}</td>
                        <td class="py-2 pr-3 text-gray-600">{{ $lead->form_name }}</td>
                        <td class="py-2 text-right">{{ App\Models\MetaLead::STATUSES[$lead->status] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-10 text-center text-gray-500">No leads this month.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
</x-report-sheet>
