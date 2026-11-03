<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-gray-900 tracking-tight">{{ $template->exists ? 'Edit template' : 'Add template' }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="p-3 rounded bg-green-50 text-green-800 text-sm">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ $template->exists ? route('admin.review-templates.update', $template) : route('admin.review-templates.store') }}" class="card p-4 sm:p-6 space-y-4">
                @csrf
                @if ($template->exists)
                    @method('PUT')
                @endif

                <div>
                    <x-input-label for="client_id" value="Client" />
                    <select id="client_id" name="client_id" class="mt-1 block w-full border-gray-300 rounded-xl shadow-sm">
                        <option value="">Default set (copied to new clients)</option>
                        @foreach ($clients as $id => $name)
                            <option value="{{ $id }}" @selected(old('client_id', $template->client_id) == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('client_id')" class="mt-2" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="rating_group" value="Rating group" />
                        <select id="rating_group" name="rating_group" class="mt-1 block w-full border-gray-300 rounded-xl shadow-sm">
                            @foreach (\App\Support\ReviewTemplates::GROUPS as $key => $label)
                                <option value="{{ $key }}" @selected(old('rating_group', $template->rating_group) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="language" value="Language" />
                        <select id="language" name="language" class="mt-1 block w-full border-gray-300 rounded-xl shadow-sm">
                            @foreach (\App\Support\ReviewTemplates::LANGUAGES as $key => $label)
                                <option value="{{ $key }}" @selected(old('language', $template->language) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <x-input-label for="body" value="Reply text" />
                    <textarea id="body" name="body" rows="5" required maxlength="2000" class="mt-1 block w-full border-gray-300 rounded-xl shadow-sm">{{ old('body', $template->body ?? '{greeting} ') }}</textarea>
                    <p class="mt-1 text-xs text-gray-500">Placeholders: {greeting} {name} {business} {phone} {city}. Never mention a patient's name, condition or treatment.</p>
                    <x-input-error :messages="$errors->get('body')" class="mt-2" />
                </div>

                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300" @checked(old('is_active', $template->is_active))>
                    <span class="text-sm text-gray-700">Active (used in rotation)</span>
                </label>

                <div class="rounded-xl bg-gray-50 p-3 text-sm space-y-2">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="font-semibold text-gray-700">Preview</span>
                        <label class="flex items-center gap-2 text-xs text-gray-600">
                            <input type="checkbox" id="preview_named" class="rounded border-gray-300" @checked(! $sample['healthcare']) @disabled($sample['healthcare'])>
                            Reviewer "Rahul Sharma" {{ $sample['healthcare'] ? '(healthcare: names are never used)' : '' }}
                        </label>
                    </div>
                    <p id="preview" class="text-gray-800 whitespace-pre-line break-words"></p>
                    <p class="text-xs text-gray-500">Sample review: ★★★★★ "Very good service, staff was helpful."</p>
                </div>

                <div class="flex items-center gap-3">
                    <x-primary-button>{{ $template->exists ? 'Save' : 'Add' }}</x-primary-button>
                    <a href="{{ route('admin.review-templates.index', array_filter(['client' => $template->client_id])) }}" class="text-sm text-gray-600 hover:underline">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        (() => {
            const greetings = @json(\App\Support\ReviewTemplates::GREETINGS);
            const sample = @json($sample);
            const body = document.getElementById('body'), language = document.getElementById('language'),
                named = document.getElementById('preview_named'), out = document.getElementById('preview');
            const render = () => {
                const [withName, noName] = greetings[language.value] || greetings.en;
                const name = named.checked ? 'Rahul' : '';
                const values = { greeting: name ? withName.replace('%s', name) : noName, name, business: sample.business, phone: sample.phone, city: sample.city };
                out.textContent = body.value.replace(/\{(greeting|name|business|phone|city)\}/g, (m, key) => values[key]);
            };
            [body, language, named].forEach((el) => el.addEventListener('input', render));
            language.addEventListener('change', render);
            render();
        })();
    </script>
</x-app-layout>
