<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-gray-900 tracking-tight">Audiences</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="p-3 rounded bg-green-50 text-green-800 text-sm">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="p-3 rounded bg-red-50 text-red-700 text-sm">{{ $errors->first() }}</div>
            @endif

            @if ($clients->isEmpty())
                <div class="card p-6 text-sm text-gray-700">No client is ready for ads yet. In Clients &gt; Edit, pick a Facebook Page and an ad account.</div>
            @else
                <form method="POST" action="{{ route('ads.audiences.store') }}" class="card p-5 space-y-4" x-data="{ type: @js(old('type', 'page')) }">
                    @csrf
                    <h3 class="font-semibold text-gray-900">New audience</h3>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="client_id" value="Client" />
                            <select id="client_id" name="client_id" class="mt-1 block w-full border-gray-300 rounded-xl shadow-sm" required>
                                @foreach ($clients as $client)
                                    <option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>{{ $client->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="name" value="Audience name" />
                            <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name')" placeholder="Page engagers – 90 days" required />
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach (App\Models\MetaAudience::TYPES as $value => $label)
                            <label class="px-3 py-2 rounded-xl border text-sm cursor-pointer" :class="type === @js($value) ? 'border-brand-600 bg-brand-50 text-brand-700' : 'border-gray-300 text-gray-700'">
                                <input type="radio" name="type" value="{{ $value }}" x-model="type" class="hidden"> {{ $label }}
                            </label>
                        @endforeach
                    </div>

                    <div x-show="['page', 'instagram', 'website'].includes(type)" class="max-w-xs">
                        <x-input-label for="days" value="People from the last … days" />
                        <x-text-input id="days" name="days" type="number" min="1" max="365" class="mt-1 block w-full" :value="old('days', 90)" />
                        <p x-show="type === 'website'" x-cloak class="text-xs text-gray-500 mt-1">Needs the client's pixel on their website (Meta ads &gt; Pixel).</p>
                    </div>

                    <div x-show="type === 'list'" x-cloak class="space-y-2">
                        <x-input-label for="contacts" value="Phones or emails (one per line)" />
                        <textarea id="contacts" name="contacts" rows="5" class="block w-full border-gray-300 rounded-xl shadow-sm text-sm" placeholder="98765 43210&#10;neha@example.com">{{ old('contacts') }}</textarea>
                        <label class="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" name="with_leads" value="1" class="rounded border-gray-300" @checked(old('with_leads'))> Also add this client's Meta leads</label>
                        <p class="text-xs text-gray-500">Contacts are scrambled (SHA-256) before they leave the app. The first list needs the Custom Audience terms accepted once in the client's ad account.</p>
                    </div>

                    <div x-show="type === 'lookalike'" x-cloak class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="source_id" value="Similar to" />
                            <select id="source_id" name="source_id" class="mt-1 block w-full border-gray-300 rounded-xl shadow-sm">
                                @foreach ($audiences->where('status', 'ready')->where('type', '!=', 'lookalike') as $a)
                                    <option value="{{ $a->id }}" @selected(old('source_id') == $a->id)>{{ $a->client->name }} · {{ $a->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="lookalike_percent" value="Size (1% = closest match)" />
                            <select id="lookalike_percent" name="lookalike_percent" class="mt-1 block w-full border-gray-300 rounded-xl shadow-sm">
                                @foreach ([1, 2, 3, 5, 10] as $p)
                                    <option value="{{ $p }}" @selected(old('lookalike_percent', 1) == $p)>{{ $p }}% of India</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button class="px-5 py-2.5 rounded-xl bg-brand-600 text-white text-sm font-semibold">Create audience</button>
                    </div>
                </form>
            @endif

            <div class="card divide-y">
                @forelse ($audiences as $audience)
                    <div class="p-4 flex flex-wrap items-start gap-3">
                        <div class="flex-1 min-w-[14rem]">
                            <div class="font-semibold">{{ $audience->name }}</div>
                            <div class="text-xs text-gray-500">{{ $audience->client->name }} · {{ App\Models\MetaAudience::TYPES[$audience->type] }} · {{ $audience->detail() }}</div>
                            @if ($audience->error)<div class="text-xs text-red-600 mt-1">{{ $audience->error }}</div>@endif
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-xs {{ $audience->status === 'ready' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-700' }}">{{ ucfirst($audience->status) }}</span>
                        <form method="POST" action="{{ route('ads.audiences.destroy', $audience) }}" onsubmit="return confirm('Delete this audience here and on Meta?')">
                            @csrf @method('DELETE')
                            <button class="text-xs text-red-600 hover:underline">Delete</button>
                        </form>
                    </div>
                @empty
                    <div class="px-4 py-6 text-center text-gray-500">No audiences yet.</div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
