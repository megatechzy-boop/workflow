<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-gray-900 tracking-tight">
            {{ $package->exists ? 'Edit Package: '.$package->name : 'New Package' }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="card p-6">
                <form method="POST" action="{{ $package->exists ? route('admin.packages.update', $package) : route('admin.packages.store') }}" class="space-y-5">
                    @csrf
                    @if ($package->exists) @method('PUT') @endif

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="name" value="Name" />
                            <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $package->name)" required autofocus />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="price" value="Price per month (₹)" />
                            <x-text-input id="price" name="price" type="number" min="0" class="mt-1 block w-full" :value="old('price', $package->price)" required />
                            <x-input-error :messages="$errors->get('price')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-input-label value="Platforms" />
                        <div class="mt-2 flex flex-wrap gap-4">
                            @foreach (App\Enums\Platform::cases() as $platform)
                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" name="platforms[]" value="{{ $platform->value }}" class="rounded border-gray-300"
                                        @checked(in_array($platform->value, old('platforms', $package->platforms ?? []), true))>
                                    {{ $platform->label() }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        @foreach (['static_posts' => 'Static posts', 'carousel_posts' => 'Carousels', 'reels' => 'Reels'] as $field => $label)
                            <div>
                                <x-input-label :for="$field" :value="$label.' / month'" />
                                <x-text-input :id="$field" :name="$field" type="number" min="0" class="mt-1 block w-full" :value="old($field, $package->$field ?? 0)" required />
                                <x-input-error :messages="$errors->get($field)" class="mt-2" />
                            </div>
                        @endforeach
                    </div>

                    <div>
                        <x-input-label for="services" value="Services (one per line)" />
                        <textarea id="services" name="services" rows="8" class="mt-1 block w-full border-gray-300 rounded-xl shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm">{{ old('services', implode("\n", $package->services ?? [])) }}</textarea>
                        <x-input-error :messages="$errors->get('services')" class="mt-2" />
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4 items-end">
                        <div>
                            <x-input-label for="min_months" value="Minimum months (optional)" />
                            <x-text-input id="min_months" name="min_months" type="number" min="1" class="mt-1 block w-full" :value="old('min_months', $package->min_months)" />
                            <x-input-error :messages="$errors->get('min_months')" class="mt-2" />
                        </div>
                        <label class="flex items-center gap-2 pb-2">
                            <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300" @checked(old('is_active', $package->is_active))>
                            <span class="text-sm text-gray-700">Active (can be given to clients)</span>
                        </label>
                    </div>

                    <div class="flex items-center gap-3">
                        <x-primary-button>{{ $package->exists ? 'Update' : 'Create' }}</x-primary-button>
                        <a href="{{ route('admin.packages.index') }}" class="text-sm text-gray-600 hover:underline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
