<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-gray-900 tracking-tight">
            {{ $calendar->client->name }} · {{ $calendar->month->format('F Y') }} — Import from sheet
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="card p-4 text-sm text-gray-700 space-y-2">
                <p><strong>How:</strong> in Excel / Google Sheets select the rows (with or without the header row), copy, and paste below.</p>
                <p><strong>Columns in this order:</strong> Sr. No. (optional) · Date · Post type · Creative copy · Visual brief · Brief · Reference link · Remarks</p>
                <p><strong>Post type</strong> = one format + platforms, e.g. <code>LinkedIn Post</code>, <code>Instagram, Facebook Reel</code>, <code>IG Carousel</code>.
                    Same day Post and Reel? Use two rows.</p>
                <p><strong>Date</strong> can be <code>11 Oct Sunday</code>, <code>11</code> or <code>2026-10-11</code> (must be in {{ $calendar->month->format('F Y') }}), or blank to set later (every row needs a date before submitting).</p>
                <p class="text-gray-500">If any row has a problem, nothing is imported and the problems are listed — fix the sheet and paste again.</p>
            </div>

            @if ($errors->has('rows'))
                <div class="p-4 rounded bg-red-50 border border-red-200 text-sm text-red-800">
                    <strong>Nothing imported. Please fix:</strong>
                    <ul class="list-disc ms-5 mt-1">
                        @foreach ($errors->get('rows') as $e)<li>{{ $e }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('calendars.import.store', $calendar) }}" class="card p-4 space-y-3">
                @csrf
                <x-input-label for="rows" value="Paste rows here" />
                <textarea id="rows" name="rows" rows="16" required class="block w-full border-gray-300 rounded-xl shadow-sm focus:border-brand-500 focus:ring-brand-500 font-mono text-xs">{{ old('rows') }}</textarea>
                <div class="flex items-center gap-3">
                    <x-primary-button>Import rows</x-primary-button>
                    <a href="{{ route('calendars.show', $calendar) }}" class="text-sm text-gray-600 hover:underline">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
