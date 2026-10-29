<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-2xl text-gray-900 tracking-tight">Clients</h2>
            <a href="{{ route('clients.create') }}" class="inline-flex items-center px-4 py-2 bg-brand-600 rounded-xl text-sm font-semibold text-white hover:bg-brand-700 min-h-[44px]">+ New Client</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="p-3 rounded bg-green-50 text-green-800 text-sm">{{ session('status') }}</div>
            @endif

            <form method="GET" class="flex flex-wrap gap-2">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Client name..." class="border-gray-300 rounded-xl shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm">
                <x-secondary-button type="submit">Search</x-secondary-button>
            </form>

            <div class="card overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Package</th>
                            <th class="px-4 py-3">Contact</th>
                            <th class="px-4 py-3">Client logins</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse ($clients as $client)
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ $client->name }}</td>
                                <td class="px-4 py-3">
                                    @if ($client->package)
                                        <div>{{ $client->package->name }}</div>
                                        @if (auth()->user()->hasPermission(App\Enums\Permission::ViewPrices))
                                            <div class="text-gray-500">{{ $client->package->priceLabel() }} / month</div>
                                        @endif
                                    @else
                                        <span class="text-gray-400">None</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div>{{ $client->contact_email }}</div>
                                    <div class="text-gray-500">{{ $client->phone }}</div>
                                </td>
                                <td class="px-4 py-3">{{ $client->users_count }}</td>
                                <td class="px-4 py-3">
                                    @if ($client->is_active)
                                        <span class="text-green-700">Active</span>
                                    @else
                                        <span class="text-red-600">Inactive</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                                    @if ($client->onboarded_at)
                                        <a href="{{ route('clients.brief', $client) }}" class="text-brand-600 hover:underline">Brief</a>
                                    @endif
                                    <a href="{{ route('clients.edit', $client) }}" class="text-brand-600 hover:underline">Edit</a>
                                    <form method="POST" action="{{ route('clients.destroy', $client) }}" class="inline" onsubmit="return confirm('Delete this client?')">
                                        @csrf @method('DELETE')
                                        <button class="text-red-600 hover:underline">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">No clients yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $clients->links() }}
        </div>
    </div>
</x-app-layout>
