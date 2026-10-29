<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-gray-900 tracking-tight">
            {{ $calendar->client->name }} · {{ $calendar->month->format('F Y') }} — {{ $item->exists ? 'Edit Post' : 'Add Post' }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="card p-6">
                <form method="POST" action="{{ $item->exists ? route('calendars.items.update', [$calendar, $item]) : route('calendars.items.store', $calendar) }}" class="space-y-5">
                    @csrf
                    @if ($item->exists) @method('PUT') @endif

                    <div class="max-w-xs">
                        <x-input-label for="post_date" value="Date to post (optional in draft)" />
                        <x-text-input id="post_date" name="post_date" type="date" class="mt-1 block w-full"
                            :min="$calendar->month->toDateString()" :max="$calendar->month->copy()->endOfMonth()->toDateString()"
                            :value="old('post_date', $item->post_date?->toDateString())" />
                        <p class="mt-1 text-xs text-gray-500">Can stay blank for now; every row needs a date before the calendar is submitted.</p>
                        <x-input-error :messages="$errors->get('post_date')" class="mt-2" />
                    </div>

                    {{-- One content per row: pick ONE format, then any platforms. Same day Post + Reel = two rows. --}}
                    @php($current = $item->post_types ?? [])
                    @php($currentFormat = $current ? App\Models\Content::typeOf($current[0])->value : null)
                    @php($currentPlatforms = array_map(fn ($k) => strstr($k, '_', true), $current))
                    <fieldset>
                        <legend class="block font-medium text-sm text-gray-700">Format (one per row)</legend>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach (App\Enums\PostType::cases() as $format)
                                <label class="flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-md text-sm cursor-pointer has-[:checked]:bg-brand-50 has-[:checked]:border-brand-400">
                                    <input type="radio" name="format" value="{{ $format->value }}" required @checked(old('format', $currentFormat) === $format->value)>
                                    {{ $format->label() }}
                                </label>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('format')" class="mt-2" />
                    </fieldset>

                    <fieldset>
                        <legend class="block font-medium text-sm text-gray-700">Platforms (one or more)</legend>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach (App\Enums\Platform::cases() as $platform)
                                <label class="flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-md text-sm cursor-pointer has-[:checked]:bg-brand-50 has-[:checked]:border-brand-400">
                                    <input type="checkbox" name="platforms[]" value="{{ $platform->value }}" class="rounded border-gray-300" @checked(in_array($platform->value, old('platforms', $currentPlatforms), true))>
                                    {{ $platform->label() }}
                                </label>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('platforms')" class="mt-2" />
                        <x-input-error :messages="$errors->get('post_types')" class="mt-2" />
                        <p class="text-xs text-gray-500 mt-1">Posting a Post and a Reel on the same day? Add two rows.</p>
                    </fieldset>

                    @foreach ([
                        'creative_copy' => 'Creative copy for post',
                        'visual_brief' => 'Visual brief',
                        'brief' => 'Brief about the post',
                    ] as $field => $label)
                        <div>
                            <x-input-label :for="$field" :value="$label" />
                            <textarea id="{{ $field }}" name="{{ $field }}" rows="3" class="mt-1 block w-full border-gray-300 rounded-xl shadow-sm focus:border-brand-500 focus:ring-brand-500">{{ old($field, $item->$field) }}</textarea>
                            <x-input-error :messages="$errors->get($field)" class="mt-2" />
                        </div>
                    @endforeach

                    <div>
                        <x-input-label for="reference_link" value="Reference link, if any" />
                        <x-text-input id="reference_link" name="reference_link" type="url" class="mt-1 block w-full" placeholder="https://" :value="old('reference_link', $item->reference_link)" />
                        <x-input-error :messages="$errors->get('reference_link')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="remarks" value="Remarks" />
                        <textarea id="remarks" name="remarks" rows="2" class="mt-1 block w-full border-gray-300 rounded-xl shadow-sm focus:border-brand-500 focus:ring-brand-500">{{ old('remarks', $item->remarks) }}</textarea>
                        <x-input-error :messages="$errors->get('remarks')" class="mt-2" />
                    </div>

                    <div class="flex items-center gap-3">
                        <x-primary-button>{{ $item->exists ? 'Update' : 'Add Post' }}</x-primary-button>
                        <a href="{{ route('calendars.show', $calendar) }}" class="text-sm text-gray-600 hover:underline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
