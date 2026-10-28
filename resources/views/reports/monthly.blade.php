<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $client->name }} · {{ $month->format('F Y') }} report</title>
    @vite(['resources/css/app.css'])
    <style>@page { size: A4; margin: 14mm; }</style>
</head>
<body class="bg-gray-100 print:bg-white text-gray-900 font-sans">
    {{-- Controls (hidden when printing) --}}
    <div class="print:hidden max-w-4xl mx-auto px-4 pt-6 flex flex-wrap items-center gap-2">
        <a href="{{ route('dashboard') }}" class="text-sm text-indigo-600 hover:underline">← Back</a>
        <form method="GET" class="flex flex-wrap items-center gap-2 ms-auto">
            @if ($brands->count() > 1)
                <select name="client" class="border-gray-300 rounded-md text-sm" aria-label="Brand">
                    @foreach ($brands as $b)
                        <option value="{{ $b->id }}" @selected($b->id === $client->id)>{{ $b->name }}</option>
                    @endforeach
                </select>
            @endif
            <input type="month" name="month" value="{{ $month->format('Y-m') }}" class="border-gray-300 rounded-md text-sm" aria-label="Month">
            <button class="px-3 py-2 rounded-md border border-gray-300 bg-white text-sm">Show</button>
            <button type="button" onclick="window.print()" class="px-3 py-2 rounded-md bg-gray-800 text-white text-sm font-semibold">📄 Save as PDF</button>
        </form>
    </div>

    <main class="max-w-4xl mx-auto my-6 print:my-0 bg-white shadow print:shadow-none rounded-lg p-8 print:p-0">
        <header class="flex items-end justify-between border-b pb-4">
            <div>
                <p class="text-sm text-gray-500">Monthly social media report</p>
                <h1 class="text-2xl font-bold">{{ $client->name }}</h1>
            </div>
            <div class="text-right">
                <div class="text-lg font-semibold">{{ $month->format('F Y') }}</div>
                <div class="text-xs text-gray-500">Generated {{ now()->format('d M Y') }}</div>
            </div>
        </header>

        <section class="grid grid-cols-5 gap-3 my-6">
            @foreach ($stats as $label => $value)
                <div class="border rounded-lg p-3 text-center">
                    <div class="text-2xl font-bold">{{ $value }}</div>
                    <div class="text-xs text-gray-600">{{ $label }}</div>
                </div>
            @endforeach
        </section>

        <table class="w-full text-sm border-collapse">
            <thead>
                <tr class="bg-gray-100 text-left">
                    <th class="p-2 border">#</th>
                    <th class="p-2 border">Date</th>
                    <th class="p-2 border">Format · Platforms</th>
                    <th class="p-2 border">Topic</th>
                    <th class="p-2 border">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    @php($content = $item->task?->contents->first())
                    <tr class="align-top break-inside-avoid">
                        <td class="p-2 border">{{ $loop->iteration }}</td>
                        <td class="p-2 border whitespace-nowrap">{{ $item->post_date->format('d M, D') }}</td>
                        <td class="p-2 border">{{ collect($item->formats())->map(fn ($f) => $item->formatLabel($f))->implode('; ') }}</td>
                        <td class="p-2 border">{{ Str::limit(strtok((string) ($item->brief ?: $item->creative_copy), "\n"), 90) }}</td>
                        <td class="p-2 border whitespace-nowrap">{{ $content?->status->label() ?? 'Planned' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-4 border text-center text-gray-500">No posts planned this month.</td></tr>
                @endforelse
            </tbody>
        </table>
    </main>
</body>
</html>
