<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $calendar->client->name }} · {{ $calendar->month->format('F Y') }} — {{ $item->exists ? 'Edit Post' : 'Add Post' }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ $item->exists ? route('calendars.items.update', [$calendar, $item]) : route('calendars.items.store', $calendar) }}" class="space-y-5">
                    @csrf
                    @if ($item->exists) @method('PUT') @endif

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="post_date" value="Date to post" />
                            <x-text-input id="post_date" name="post_date" type="date" class="mt-1 block w-full"
                                :min="$calendar->month->toDateString()" :max="$calendar->month->copy()->endOfMonth()->toDateString()"
                                :value="old('post_date', $item->post_date?->toDateString())" required />
                            <x-input-error :messages="$errors->get('post_date')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="post_type" value="Post type" />
                            <select id="post_type" name="post_type" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                                @foreach (App\Enums\PostType::cases() as $type)
                                    <option value="{{ $type->value }}" @selected(old('post_type', $item->post_type?->value) === $type->value)>{{ $type->label() }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('post_type')" class="mt-2" />
                        </div>
                    </div>

                    @foreach ([
                        'creative_copy' => 'Creative copy for post',
                        'visual_brief' => 'Visual brief',
                        'brief' => 'Brief about the post',
                    ] as $field => $label)
                        <div>
                            <x-input-label :for="$field" :value="$label" />
                            <textarea id="{{ $field }}" name="{{ $field }}" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">{{ old($field, $item->$field) }}</textarea>
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
                        <textarea id="remarks" name="remarks" rows="2" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">{{ old('remarks', $item->remarks) }}</textarea>
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
