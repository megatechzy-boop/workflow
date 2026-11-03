<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-bold text-2xl text-gray-900 tracking-tight">Review templates</h2>
            <a href="{{ route('admin.review-templates.create', array_filter(['client' => $filters['client'] ?? null])) }}" class="inline-flex items-center px-4 py-2 bg-brand-600 rounded-xl text-sm font-semibold text-white hover:bg-brand-700 min-h-[44px]">Add template</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="p-3 rounded bg-green-50 text-green-800 text-sm">{{ session('status') }}</div>
            @endif

            <p class="text-sm text-gray-600">
                4–5 star reviews get a positive template automatically (when the client's auto reply is on); 3 stars get a neutral draft for approval.
                Keep 5–10 active templates per group and language: the last 3 used on a location are never repeated.
                Placeholders: <code>{greeting}</code> <code>{name}</code> <code>{business}</code> <code>{phone}</code> <code>{city}</code>.
                The default set is copied to every new client.
            </p>

            <form method="GET" class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                <select name="client" onchange="this.form.submit()" class="border-gray-300 rounded-xl shadow-sm text-sm min-h-[44px]" aria-label="Client">
                    <option value="">Default set</option>
                    @foreach ($clients as $id => $name)
                        <option value="{{ $id }}" @selected(($filters['client'] ?? null) == $id)>{{ $name }}</option>
                    @endforeach
                </select>
                <select name="group" onchange="this.form.submit()" class="border-gray-300 rounded-xl shadow-sm text-sm min-h-[44px]" aria-label="Rating group">
                    <option value="">Both groups</option>
                    @foreach (\App\Support\ReviewTemplates::GROUPS as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['group'] ?? null) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="language" onchange="this.form.submit()" class="border-gray-300 rounded-xl shadow-sm text-sm min-h-[44px]" aria-label="Language">
                    <option value="">All languages</option>
                    @foreach (\App\Support\ReviewTemplates::LANGUAGES as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['language'] ?? null) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <noscript><button class="px-3 py-2 border rounded-xl">Filter</button></noscript>
            </form>

            <div class="card divide-y">
                @forelse ($templates->groupBy(fn ($t) => $t->rating_group.'.'.$t->language) as $key => $group)
                    @php([$ratingGroup, $language] = explode('.', $key))
                    <div class="px-4 py-2 bg-gray-50 text-xs font-semibold text-gray-600 flex justify-between gap-2">
                        <span>{{ ucfirst($ratingGroup) }} · {{ \App\Support\ReviewTemplates::LANGUAGES[$language] }}</span>
                        @php($active = $counts[$key] ?? 0)
                        <span class="{{ $active < 4 ? 'text-amber-700' : '' }}">{{ $active }} active{{ $active < 4 ? ' (add more to avoid repeats)' : '' }}</span>
                    </div>
                    @foreach ($group as $template)
                        <div class="px-4 py-3 flex flex-col sm:flex-row sm:items-start gap-2 text-sm">
                            <p class="flex-1 min-w-0 break-words {{ $template->is_active ? 'text-gray-800' : 'text-gray-400 line-through' }}">{{ $template->body }}</p>
                            <div class="flex gap-2 shrink-0">
                                <a href="{{ route('admin.review-templates.edit', $template) }}" class="px-3 py-2 rounded-xl border border-gray-300 text-gray-700 min-h-[44px] inline-flex items-center">Edit</a>
                                <form method="POST" action="{{ route('admin.review-templates.duplicate', $template) }}">
                                    @csrf
                                    <button class="px-3 py-2 rounded-xl border border-gray-300 text-gray-700 min-h-[44px]">Duplicate</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                @empty
                    <div class="px-4 py-6 text-center text-gray-500">No templates.</div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
