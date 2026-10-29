<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-bold text-2xl text-gray-900 tracking-tight">Attendance</h2>
            @include('office._month')
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="p-3 rounded bg-green-50 text-green-800 text-sm">{{ session('status') }}</div>
            @endif

            <p class="text-sm text-gray-600">{{ $workingDays }} working days (Sundays off). Team checks in from Home; open a name to mark half days or leave.</p>

            <div class="card overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3 text-center">Present</th>
                            <th class="px-4 py-3 text-center">Half day</th>
                            <th class="px-4 py-3 text-center">Leave</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($staff as $user)
                            @php($mine = $rows[$user->id] ?? collect())
                            <tr class="{{ $member?->is($user) ? 'bg-brand-50' : '' }}">
                                <td class="px-4 py-3">
                                    <a href="{{ request()->fullUrlWithQuery(['user' => $user->id]) }}" class="font-medium text-brand-600 hover:underline">{{ $user->name }}</a>
                                    <div class="text-xs text-gray-500">{{ $user->role->label() }}</div>
                                </td>
                                <td class="px-4 py-3 text-center">{{ $mine->where('status', 'present')->count() }}</td>
                                <td class="px-4 py-3 text-center">{{ $mine->where('status', 'half_day')->count() }}</td>
                                <td class="px-4 py-3 text-center">{{ $mine->where('status', 'leave')->count() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($member)
                <div class="card">
                    <div class="px-4 py-3 border-b border-gray-100 font-semibold">{{ $member->name }} · {{ $month->format('F Y') }}</div>
                    <div class="divide-y">
                        @for ($d = $month; $d->lte($month->endOfMonth()); $d = $d->addDay())
                            @php($day = $days[$d->format('Y-m-d')] ?? null)
                            <form method="POST" action="{{ route('admin.attendance.update') }}" class="flex flex-wrap items-center gap-3 px-4 py-2 text-sm {{ $d->isSunday() ? 'bg-gray-50 text-gray-400' : '' }}">
                                @csrf
                                <input type="hidden" name="user_id" value="{{ $member->id }}">
                                <input type="hidden" name="date" value="{{ $d->format('Y-m-d') }}">
                                <span class="w-24">{{ $d->format('D d M') }}</span>
                                <span class="w-32 text-gray-500">
                                    @if ($day?->check_in_at)
                                        {{ $day->check_in_at->format('h:i A') }} – {{ $day->check_out_at?->format('h:i A') ?? '…' }}
                                    @endif
                                </span>
                                <select name="status" onchange="this.form.submit()" class="border-gray-300 rounded-xl text-sm py-1" aria-label="Status for {{ $d->format('d M') }}">
                                    <option value="">{{ $d->isSunday() ? 'Sunday' : 'Absent' }}</option>
                                    @foreach (App\Models\Attendance::STATUSES as $value => $label)
                                        <option value="{{ $value }}" @selected($day?->status === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </form>
                        @endfor
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
