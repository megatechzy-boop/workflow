<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-bold text-2xl text-gray-900 tracking-tight">Expenses</h2>
            @include('office._month')
        </div>
    </x-slot>

    @php($profit = $summary['received'] - $summary['salaries'] - $summary['expenses'])
    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="p-3 rounded bg-green-50 text-green-800 text-sm">{{ session('status') }}</div>
            @endif

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="card p-4"><div class="text-sm text-gray-500">Received from clients</div><div class="text-xl font-bold">₹{{ number_format($summary['received']) }}</div></div>
                <div class="card p-4"><div class="text-sm text-gray-500">Salaries paid</div><div class="text-xl font-bold">₹{{ number_format($summary['salaries']) }}</div></div>
                <div class="card p-4"><div class="text-sm text-gray-500">Expenses</div><div class="text-xl font-bold">₹{{ number_format($summary['expenses']) }}</div></div>
                <div class="card p-4"><div class="text-sm text-gray-500">Profit</div><div class="text-xl font-bold {{ $profit < 0 ? 'text-red-600' : 'text-green-700' }}">{{ $profit < 0 ? '−' : '' }}₹{{ number_format(abs($profit)) }}</div></div>
            </div>

            <form method="POST" action="{{ route('admin.expenses.store') }}" class="card p-4 grid sm:grid-cols-[9rem_1fr_10rem_8rem_auto] gap-3 items-end">
                @csrf
                <div>
                    <x-input-label for="date" value="Date" />
                    <x-text-input id="date" name="date" type="date" class="mt-1 block w-full" :value="old('date', today()->format('Y-m-d'))" required />
                </div>
                <div>
                    <x-input-label for="title" value="What" />
                    <x-text-input id="title" name="title" class="mt-1 block w-full" :value="old('title')" placeholder="Office rent" required />
                </div>
                <div>
                    <x-input-label for="category" value="Category" />
                    <select id="category" name="category" class="mt-1 block w-full border-gray-300 rounded-xl shadow-sm">
                        @foreach (App\Models\Expense::CATEGORIES as $value => $label)
                            <option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="amount" value="Amount (₹)" />
                    <x-text-input id="amount" name="amount" type="number" min="1" class="mt-1 block w-full" :value="old('amount')" required />
                </div>
                <button class="px-4 py-2 min-h-[42px] rounded-xl bg-brand-600 text-white text-sm font-semibold">Add</button>
                <x-input-error :messages="$errors->all()" class="sm:col-span-5" />
            </form>

            <div class="card divide-y">
                @forelse ($expenses as $expense)
                    <div class="flex items-center gap-3 px-4 py-3 text-sm">
                        <span class="w-16 text-gray-500">{{ $expense->date->format('d M') }}</span>
                        <span class="flex-1">
                            <span class="font-medium">{{ $expense->title }}</span>
                            <span class="block text-xs text-gray-500">{{ App\Models\Expense::CATEGORIES[$expense->category] ?? $expense->category }}</span>
                        </span>
                        <span class="font-semibold">₹{{ number_format($expense->amount) }}</span>
                        <form method="POST" action="{{ route('admin.expenses.destroy', $expense) }}" onsubmit="return confirm('Delete this expense?')">
                            @csrf @method('DELETE')
                            <button class="p-1 text-gray-400 hover:text-red-600" aria-label="Delete {{ $expense->title }}"><x-icon name="x" class="w-4 h-4" /></button>
                        </form>
                    </div>
                @empty
                    <div class="px-4 py-6 text-center text-gray-500">No expenses for {{ $month->format('F Y') }}.</div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
