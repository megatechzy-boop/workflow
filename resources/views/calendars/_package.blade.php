{{-- Client's package: posts planned vs the monthly quota, and what else is included (team only, no price) --}}
@php($counts = $calendar->formatCounts())
<div class="card p-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="font-semibold text-gray-900">{{ $package->name }} package</div>
        <div class="text-sm text-gray-500">
            {{ $package->platformLabel() }}
            @if ($calendar->client->onboarded_at && (auth()->user()->hasPermission(App\Enums\Permission::ManageClients) || auth()->user()->hasPermission(App\Enums\Permission::ManageCalendars)))
                · <a href="{{ route('clients.brief', $calendar->client) }}" class="text-brand-600 hover:underline">Client brief</a>
            @endif
        </div>
    </div>
    <div class="mt-3 grid grid-cols-3 gap-3 max-w-md">
        @foreach (App\Enums\PostType::cases() as $type)
            @php([$done, $quota] = [$counts[$type->value], $package->quota($type)])
            <div class="rounded-xl px-3 py-2 {{ $done > $quota ? 'bg-accent-50 text-accent-600' : ($done === $quota ? 'bg-brand-50 text-brand-700' : 'bg-gray-50 text-gray-700') }}">
                <div class="text-xs">{{ $type === App\Enums\PostType::Post ? 'Static' : $type->label() }}</div>
                <div class="text-lg font-bold">{{ $done }}/{{ $quota }}</div>
            </div>
        @endforeach
    </div>
    @foreach (App\Enums\PostType::cases() as $type)
        @if ($counts[$type->value] > $package->quota($type))
            <p class="mt-2 text-sm text-accent-600">{{ $type === App\Enums\PostType::Post ? 'Static posts' : Str::plural($type->label()) }}: {{ $counts[$type->value] - $package->quota($type) }} more than the package.</p>
        @endif
    @endforeach
    @unless (auth()->user()->role->isCreator())
        <details class="mt-3 text-sm">
            <summary class="cursor-pointer text-brand-600">Services included</summary>
            <ul class="mt-2 grid sm:grid-cols-2 gap-1 text-gray-700">
                @foreach ($package->services as $service)
                    <li class="flex gap-2"><x-icon name="tick" class="w-4 h-4 mt-0.5 text-brand-600 shrink-0" />{{ $service }}</li>
                @endforeach
            </ul>
        </details>
    @endunless
</div>
