<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-bold text-2xl text-gray-900 tracking-tight">{{ $client->name }} · Brief</h2>
            <p class="text-sm text-gray-500 mt-1">
                {{ $client->onboarded_at ? 'From the onboarding form, '.$client->onboarded_at->format('d M Y') : 'The client has not filled the onboarding form yet.' }}
            </p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @php($sections = App\Support\OnboardingForm::sections())
            @php($industry = $answers['industry'] ?? null)
            @if ($industry)
                @php($sections['Industry: '.$industry] = array_map(fn ($f) => [$f[0], $f[1], 'text'], App\Support\OnboardingForm::industries()[$industry] ?? []))
            @endif

            @foreach ($sections as $title => $fields)
                @php($filled = collect($fields)->filter(fn ($f) => filled($answers[$f[0]] ?? null)))
                @if ($filled->isNotEmpty())
                    <section class="card p-5">
                        <h3 class="font-semibold text-gray-900 mb-3">{{ $title }}</h3>
                        <dl class="grid sm:grid-cols-[14rem_1fr] gap-x-4 gap-y-2 text-sm">
                            @foreach ($filled as [$key, $label])
                                <dt class="text-gray-500">{{ $label }}</dt>
                                <dd class="text-gray-800 whitespace-pre-line break-words">{{ is_array($answers[$key]) ? implode(', ', $answers[$key]) : $answers[$key] }}</dd>
                            @endforeach
                        </dl>
                    </section>
                @endif
            @endforeach
        </div>
    </div>
</x-app-layout>
