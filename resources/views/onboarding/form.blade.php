<x-public-layout title="Client onboarding">
    <form method="POST" action="{{ route('onboarding.submit', $client->onboarding_token) }}" class="space-y-6" x-data="{ industry: @js(old('industry', $answers['industry'] ?? '')) }">
        @csrf
        <div class="card p-5 text-sm text-gray-700">
            Welcome, <strong>{{ $client->name }}</strong>. These details help our team plan your content and campaigns.
            Everything is optional except the confirmation at the end. You can open this link again later to update your answers.
        </div>

        @if ($errors->any())
            <div class="p-3 rounded bg-red-50 text-red-700 text-sm">{{ $errors->first() }}</div>
        @endif

        @foreach (App\Support\OnboardingForm::sections() as $title => $fields)
            <section class="card p-5 space-y-4">
                <h2 class="font-bold text-lg text-gray-900"><span class="text-brand-600">{{ sprintf('%02d', $loop->iteration) }}.</span> {{ $title }}</h2>
                @foreach ($fields as $field)
                    @php([$key, $label, $type] = $field)
                    @php($value = old($key, $answers[$key] ?? ($key === 'business_name' ? $client->name : null)))
                    @if (in_array($type, ['radio', 'checkbox']))
                        <fieldset>
                            <legend class="text-sm font-medium text-gray-700">{{ $label }}</legend>
                            <div class="mt-2 grid sm:grid-cols-2 gap-2">
                                @foreach ($field[3] as $option)
                                    <label class="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="{{ $type }}" name="{{ $type === 'checkbox' ? $key.'[]' : $key }}" value="{{ $option }}"
                                            class="{{ $type === 'checkbox' ? 'rounded' : '' }} border-gray-300"
                                            @checked($type === 'checkbox' ? in_array($option, (array) $value, true) : $value === $option)>
                                        {{ $option }}
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @elseif ($type === 'textarea')
                        <div>
                            <x-input-label :for="$key" :value="$label" />
                            <textarea id="{{ $key }}" name="{{ $key }}" rows="3" class="mt-1 block w-full border-gray-300 rounded-xl shadow-sm">{{ $value }}</textarea>
                        </div>
                    @else
                        <div>
                            <x-input-label :for="$key" :value="$label" />
                            <x-text-input :id="$key" :name="$key" :type="$type" class="mt-1 block w-full" :value="$value" />
                        </div>
                    @endif
                @endforeach
            </section>
        @endforeach

        <section class="card p-5 space-y-4">
            <h2 class="font-bold text-lg text-gray-900"><span class="text-brand-600">09.</span> Industry-specific context</h2>
            <div>
                <x-input-label for="industry" value="Select your industry" />
                <select id="industry" name="industry" x-model="industry" class="mt-1 block w-full border-gray-300 rounded-xl shadow-sm">
                    <option value="">Select industry</option>
                    @foreach (array_keys(App\Support\OnboardingForm::industries()) as $industry)
                        <option>{{ $industry }}</option>
                    @endforeach
                </select>
            </div>
            @foreach (App\Support\OnboardingForm::industries() as $industry => $fields)
                <div x-show="industry === @js($industry)" x-cloak class="space-y-4">
                    @foreach ($fields as [$key, $label])
                        <div>
                            <x-input-label :for="$key" :value="$label" />
                            <x-text-input :id="$key" :name="$key" class="mt-1 block w-full" :value="old($key, $answers[$key] ?? null)" />
                        </div>
                    @endforeach
                </div>
            @endforeach
        </section>

        <div class="card p-5 space-y-4">
            <label class="flex items-start gap-2 text-sm text-gray-700">
                <input type="checkbox" name="consent" value="1" class="mt-1 rounded border-gray-300" required>
                <span>I confirm the information is correct and I agree to Mega Techzy storing it for onboarding and project work. I understand that services not included in my chosen package are charged extra. *</span>
            </label>
            <button class="w-full py-3 rounded-xl bg-brand-600 text-white font-semibold">Submit</button>
        </div>
    </form>
</x-public-layout>
