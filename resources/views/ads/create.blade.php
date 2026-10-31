<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-gray-900 tracking-tight">New ad</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            @if ($clients->isEmpty())
                <div class="card p-6 text-sm text-gray-700">No client is ready for ads yet. In Clients &gt; Edit, pick a Facebook Page and an ad account.</div>
            @else
                <form method="POST" action="{{ route('ads.store') }}" enctype="multipart/form-data" class="space-y-4" x-data="{ goal: @js(old('goal', 'whatsapp')) }">
                    @csrf
                    @if ($errors->any())
                        <div class="p-3 rounded bg-red-50 text-red-700 text-sm">{{ $errors->first() }}</div>
                    @endif

                    <section class="card p-5 space-y-4">
                        <h3 class="font-semibold text-gray-900">1. Goal</h3>
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
                                <x-input-label for="name" value="Ad name" />
                                <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name')" placeholder="Diwali offer – WhatsApp" required />
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @foreach (App\Models\MetaAd::GOALS as $value => $label)
                                <label class="px-3 py-2 rounded-xl border text-sm cursor-pointer" :class="goal === @js($value) ? 'border-brand-600 bg-brand-50 text-brand-700' : 'border-gray-300 text-gray-700'">
                                    <input type="radio" name="goal" value="{{ $value }}" x-model="goal" class="hidden"> {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        <div x-show="goal === 'website'" x-cloak>
                            <x-input-label for="url" value="Website link" />
                            <x-text-input id="url" name="url" type="url" class="mt-1 block w-full" :value="old('url')" placeholder="https://…" />
                        </div>
                        <div x-show="goal === 'calls'" x-cloak>
                            <x-input-label for="phone" value="Phone to call" />
                            <x-text-input id="phone" name="phone" class="mt-1 block w-full" :value="old('phone')" placeholder="+91 98765 43210" />
                        </div>
                        <p x-show="goal === 'whatsapp'" class="text-xs text-gray-500">Uses the WhatsApp number connected to the client's Facebook Page.</p>
                    </section>

                    <section class="card p-5 space-y-4">
                        <h3 class="font-semibold text-gray-900">2. Audience</h3>
                        <div class="grid sm:grid-cols-4 gap-4">
                            <div class="sm:col-span-2">
                                <x-input-label for="city" value="City (empty = all India)" />
                                <x-text-input id="city" name="city" class="mt-1 block w-full" :value="old('city')" placeholder="Solapur" />
                            </div>
                            <div>
                                <x-input-label for="radius_km" value="Radius km" />
                                <x-text-input id="radius_km" name="radius_km" type="number" min="1" max="80" class="mt-1 block w-full" :value="old('radius_km', 15)" />
                            </div>
                            <div>
                                <x-input-label for="gender" value="Gender" />
                                <select id="gender" name="gender" class="mt-1 block w-full border-gray-300 rounded-xl shadow-sm">
                                    @foreach (['all' => 'All', 'male' => 'Men', 'female' => 'Women'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('gender', 'all') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label for="age_min" value="Age from" />
                                <x-text-input id="age_min" name="age_min" type="number" min="18" max="65" class="mt-1 block w-full" :value="old('age_min', 22)" required />
                            </div>
                            <div>
                                <x-input-label for="age_max" value="Age to" />
                                <x-text-input id="age_max" name="age_max" type="number" min="18" max="65" class="mt-1 block w-full" :value="old('age_max', 55)" required />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label for="interests" value="Interests (comma separated, optional)" />
                                <x-text-input id="interests" name="interests" class="mt-1 block w-full" :value="old('interests')" placeholder="Yoga, Fitness" />
                            </div>
                        </div>
                    </section>

                    <section class="card p-5 space-y-4">
                        <h3 class="font-semibold text-gray-900">3. Budget and dates</h3>
                        <div class="grid sm:grid-cols-3 gap-4">
                            <div>
                                <x-input-label for="daily_budget" value="₹ per day" />
                                <x-text-input id="daily_budget" name="daily_budget" type="number" min="100" class="mt-1 block w-full" :value="old('daily_budget', 300)" required />
                            </div>
                            <div>
                                <x-input-label for="start_on" value="Start" />
                                <x-text-input id="start_on" name="start_on" type="date" class="mt-1 block w-full" :value="old('start_on', today()->format('Y-m-d'))" required />
                            </div>
                            <div>
                                <x-input-label for="end_on" value="End" />
                                <x-text-input id="end_on" name="end_on" type="date" class="mt-1 block w-full" :value="old('end_on', today()->addDays(7)->format('Y-m-d'))" required />
                            </div>
                        </div>
                    </section>

                    <section class="card p-5 space-y-4">
                        <h3 class="font-semibold text-gray-900">4. Ad</h3>
                        <div>
                            <x-input-label for="image" value="Image (jpg/png, square or 4:5)" />
                            <input id="image" name="image" type="file" accept="image/jpeg,image/png" class="mt-1 block w-full text-sm" required>
                        </div>
                        <div>
                            <x-input-label for="primary_text" value="Text" />
                            <textarea id="primary_text" name="primary_text" rows="3" class="mt-1 block w-full border-gray-300 rounded-xl shadow-sm" required>{{ old('primary_text') }}</textarea>
                        </div>
                        <div>
                            <x-input-label for="headline" value="Headline (optional)" />
                            <x-text-input id="headline" name="headline" class="mt-1 block w-full" :value="old('headline')" />
                        </div>
                    </section>

                    <div class="card p-4 flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm text-gray-600">The ad is created <strong>paused</strong>. Nothing is spent until you press Start on the Ads page.</p>
                        <button class="px-5 py-2.5 rounded-xl bg-brand-600 text-white text-sm font-semibold">Create ad</button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
