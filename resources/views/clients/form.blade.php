<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-gray-900 tracking-tight">
            {{ $client->exists ? 'Edit Client: '.$client->name : 'New Client' }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
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

                    <div>
                        <x-input-label for="notes" value="Notes" />
                        <textarea id="notes" name="notes" rows="4" class="mt-1 block w-full border-gray-300 rounded-xl shadow-sm focus:border-brand-500 focus:ring-brand-500">{{ old('notes', $client->notes) }}</textarea>
                        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                    </div>

                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300" @checked(old('is_active', $client->is_active))>
                        <span class="text-sm text-gray-700">Active</span>
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
