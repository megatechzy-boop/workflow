<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-2xl text-gray-900 tracking-tight">Users</h2>
            <div class="flex items-center gap-2">
                @if (auth()->user()->isAdmin())
                    <form method="POST" action="{{ route('admin.mail.test') }}">
                        @csrf
                        <button class="inline-flex items-center gap-1.5 px-3 py-2 min-h-[44px] border border-gray-300 rounded-xl text-sm font-semibold text-gray-700 hover:bg-gray-50"><x-icon name="mail" class="w-4 h-4" /> Send test email to me</button>
                    </form>
                @endif
                <a href="{{ route('admin.users.create') }}" class="inline-flex items-center px-4 py-2 bg-brand-600 rounded-xl text-sm font-semibold text-white hover:bg-brand-700 min-h-[44px]">+ New User</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="p-3 rounded bg-green-50 text-green-800 text-sm">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="p-3 rounded bg-red-50 text-red-800 text-sm">{{ session('error') }}</div>
            @endif

            <form method="GET" class="flex flex-wrap gap-2">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Name or email..." class="border-gray-300 rounded-xl shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm">
                <select name="role" class="border-gray-300 rounded-xl shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm">
                    <option value="">All roles</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->value }}" @selected(request('role') === $role->value)>{{ $role->label() }}</option>
                    @endforeach
                </select>
                <x-secondary-button type="submit">Filter</x-secondary-button>
            </form>

            <div class="card overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3">Role</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse ($users as $user)
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ $user->name }}</td>
                                <td class="px-4 py-3">{{ $user->email }}</td>
                                <td class="px-4 py-3"><span class="px-2 py-1 rounded bg-brand-50 text-brand-700 text-xs">{{ $user->role->label() }}</span>
                                    @if ($user->client)<span class="text-xs text-gray-500"> · {{ $user->client->name }}</span>@endif
                                    @if ($user->brands->isNotEmpty())<span class="text-xs text-gray-500"> · {{ $user->brands->pluck('name')->join(', ') }}</span>@endif
                                </td>
                                <td class="px-4 py-3">
                                    @if ($user->is_active)
                                        <span class="text-green-700">Active</span>
                                    @else
                                        <span class="text-red-600">Inactive</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                                    <a href="{{ route('admin.users.edit', $user) }}" class="text-brand-600 hover:underline">Edit</a>
                                    @unless ($user->is(auth()->user()))
                                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="inline" onsubmit="return confirm('Delete this user?')">
                                            @csrf @method('DELETE')
                                            <button class="text-red-600 hover:underline">Delete</button>
                                        </form>
                                    @endunless
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">No users found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $users->links() }}
        </div>
    </div>
</x-app-layout>
