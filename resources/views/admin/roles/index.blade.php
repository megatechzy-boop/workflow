<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Roles &amp; Permissions</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="p-3 rounded bg-green-50 text-green-800 text-sm">{{ session('status') }}</div>
            @endif

            <p class="text-sm text-gray-600">
                Har role ke sab members ko ye permissions milti hain. Kisi ek member ko extra dena ho toh <strong>Users &gt; Edit</strong> mein do.
                Admin ke paas hamesha sab permissions hain; Customer ko internal permissions nahi milti.
            </p>

            <form method="POST" action="{{ route('admin.roles.update') }}" class="space-y-4">
                @csrf @method('PUT')
                <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-600">
                            <tr>
                                <th class="px-4 py-3 text-left">Permission</th>
                                <th class="px-4 py-3 text-center text-gray-400">Admin</th>
                                @foreach ($roles as $role)
                                    <th class="px-4 py-3 text-center">{{ $role->label() }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach ($permissions as $permission)
                                <tr>
                                    <td class="px-4 py-3">{{ $permission->label() }}</td>
                                    <td class="px-4 py-3 text-center"><input type="checkbox" checked disabled class="rounded border-gray-300 text-gray-400" aria-label="Admin: {{ $permission->label() }}"></td>
                                    @foreach ($roles as $role)
                                        <td class="px-4 py-3 text-center">
                                            <input type="checkbox" name="grant[{{ $role->value }}][]" value="{{ $permission->value }}"
                                                class="rounded border-gray-300" aria-label="{{ $role->label() }}: {{ $permission->label() }}"
                                                @checked(in_array($permission->value, $granted[$role->value] ?? [], true))>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <x-primary-button>Save</x-primary-button>
            </form>

            <p class="text-xs text-gray-500">
                Ye workflow niyam permission se nahi badalte: task wala Designer/Editor hi apna content upload karta hai,
                content upload par chuna gaya Approver hi review karta hai, aur Customer sirf apne brand ka calendar approve karta hai.
            </p>
        </div>
    </div>
</x-app-layout>
