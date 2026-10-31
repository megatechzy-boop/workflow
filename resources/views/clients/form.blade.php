<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-gray-900 tracking-tight">
            {{ $client->exists ? 'Edit Client: '.$client->name : 'New Client' }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="p-3 rounded bg-green-50 text-green-800 text-sm">{{ session('status') }}</div>
            @endif

            @if ($client->exists)
                {{-- Onboarding: private link for the client; answers become the team's brief --}}
                <div class="card p-5 space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <div class="font-semibold">Onboarding form</div>
                        @if ($client->onboarded_at)
                            <a href="{{ route('clients.brief', $client) }}" class="text-sm text-brand-600 hover:underline">View brief (filled {{ $client->onboarded_at->format('d M') }})</a>
                        @else
                            <span class="text-sm text-gray-500">Not filled yet</span>
                        @endif
                    </div>
                    @if ($client->onboarding_token)
                        @php($link = route('onboarding.form', $client->onboarding_token))
                        <div class="text-sm break-all text-gray-600">{{ $link }}</div>
                        <div class="flex flex-wrap gap-2" x-data>
                            <button type="button" @click="navigator.clipboard.writeText(@js($link)); $el.textContent = 'Copied'" class="px-3 py-2 rounded-xl border border-gray-300 text-sm font-semibold text-gray-700">Copy link</button>
                            <a href="https://wa.me/?text={{ rawurlencode("Hi! Please fill this onboarding form so we can get started: $link") }}" target="_blank" rel="noopener" class="px-3 py-2 rounded-xl bg-green-50 text-green-800 text-sm font-semibold">Send on WhatsApp</a>
                        </div>
                    @else
                        <form method="POST" action="{{ route('clients.onboardingLink', $client) }}">
                            @csrf
                            <button class="px-4 py-2 rounded-xl bg-brand-600 text-white text-sm font-semibold">Create onboarding link</button>
                        </form>
                    @endif
                </div>
            @endif

            <div class="card p-6">
                <form method="POST" action="{{ $client->exists ? route('clients.update', $client) : route('clients.store') }}" class="space-y-5">
                    @csrf
                    @if ($client->exists) @method('PUT') @endif

                    <div>
                        <x-input-label for="name" value="Client / Brand Name" />
                        <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $client->name)" required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="contact_email" value="Contact Email" />
                        <x-text-input id="contact_email" name="contact_email" type="email" class="mt-1 block w-full" :value="old('contact_email', $client->contact_email)" />
                        <x-input-error :messages="$errors->get('contact_email')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="phone" value="Phone" />
                        <x-text-input id="phone" name="phone" class="mt-1 block w-full" :value="old('phone', $client->phone)" />
                        <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="address" value="Billing address (shown on invoices)" />
                        <textarea id="address" name="address" rows="3" class="mt-1 block w-full border-gray-300 rounded-xl shadow-sm focus:border-brand-500 focus:ring-brand-500">{{ old('address', $client->address) }}</textarea>
                        <x-input-error :messages="$errors->get('address')" class="mt-2" />
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="package_id" value="Package" />
                            <select id="package_id" name="package_id" class="mt-1 block w-full border-gray-300 rounded-xl shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                <option value="">No package</option>
                                @foreach ($packages as $package)
                                    <option value="{{ $package->id }}" @selected(old('package_id', $client->package_id) == $package->id)>
                                        {{ $package->name }}@if (auth()->user()->hasPermission(App\Enums\Permission::ViewPrices)) · {{ $package->priceLabel() }}@endif
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('package_id')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="package_start" value="Package start date" />
                            <x-text-input id="package_start" name="package_start" type="date" class="mt-1 block w-full" :value="old('package_start', $client->package_start?->format('Y-m-d'))" />
                            <x-input-error :messages="$errors->get('package_start')" class="mt-2" />
                        </div>
                    </div>

                    @if ($metaPages->isNotEmpty())
                        <div>
                            <x-input-label for="meta_page_id" value="Facebook Page (approved posts publish here and on its Instagram)" />
                            <select id="meta_page_id" name="meta_page_id" class="mt-1 block w-full border-gray-300 rounded-xl shadow-sm">
                                <option value="">Not linked</option>
                                @foreach ($metaPages as $metaPage)
                                    <option value="{{ $metaPage->id }}" @selected(old('meta_page_id', $client->meta_page_id) == $metaPage->id)>{{ $metaPage->label() }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('meta_page_id')" class="mt-2" />
                        </div>
                    @endif

                    @if ($adAccounts->isNotEmpty())
                        <div>
                            <x-input-label for="meta_ad_account_id" value="Ad account (boosts are charged here: the client's own account)" />
                            <select id="meta_ad_account_id" name="meta_ad_account_id" class="mt-1 block w-full border-gray-300 rounded-xl shadow-sm">
                                <option value="">Not linked</option>
                                @foreach ($adAccounts as $adAccount)
                                    <option value="{{ $adAccount->id }}" @selected(old('meta_ad_account_id', $client->meta_ad_account_id) == $adAccount->id)>{{ $adAccount->label() }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('meta_ad_account_id')" class="mt-2" />
                        </div>
                    @endif

                    <div>
                        <x-input-label for="notes" value="Notes" />
                        <textarea id="notes" name="notes" rows="4" class="mt-1 block w-full border-gray-300 rounded-xl shadow-sm focus:border-brand-500 focus:ring-brand-500">{{ old('notes', $client->notes) }}</textarea>
                        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                    </div>

                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300" @checked(old('is_active', $client->is_active))>
                        <span class="text-sm text-gray-700">Active</span>
                    </label>

                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_internal" value="1" class="rounded border-gray-300" @checked(old('is_internal', $client->is_internal))>
                        <span class="text-sm text-gray-700">Internal brand (our own, e.g. Mega Techzy): no Customer approval step</span>
                    </label>

                    <div class="flex items-center gap-3">
                        <x-primary-button>{{ $client->exists ? 'Update' : 'Create' }}</x-primary-button>
                        <a href="{{ route('clients.index') }}" class="text-sm text-gray-600 hover:underline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
