<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $user->exists ? 'Edit User: '.$user->name : 'New User' }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" class="space-y-5">
                    @csrf
                    @if ($user->exists) @method('PUT') @endif

                    <div>
                        <x-input-label for="name" value="Name" />
                        <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="email" value="Email" />
                        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    @php($isSelf = $user->exists && $user->is(auth()->user()))

                    <div>
                        <x-input-label for="role" value="Role" />
                        <select id="role" name="role" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" @disabled($isSelf)>
                            @foreach ($roles as $role)
                                <option value="{{ $role->value }}" @selected(old('role', $user->role?->value) === $role->value)>{{ $role->label() }}</option>
                            @endforeach
                        </select>
                        @if ($isSelf)
                            <input type="hidden" name="role" value="{{ $user->role->value }}">
                            <p class="text-xs text-gray-500 mt-1">You cannot change your own role.</p>
                        @endif
                        <x-input-error :messages="$errors->get('role')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="client_id" value="Brand (Customer role only)" />
                        <select id="client_id" name="client_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                            <option value="">— None —</option>
                            @foreach ($clients as $c)
                                <option value="{{ $c->id }}" @selected((string) old('client_id', $user->client_id) === (string) $c->id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('client_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="password" :value="$user->exists ? 'New Password (leave empty to keep the current one)' : 'Password'" />
                        <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="password_confirmation" value="Confirm Password" />
                        <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                    </div>

                    @unless ($isSelf)
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300" @checked(old('is_active', $user->is_active))>
                            <span class="text-sm text-gray-700">Active (can log in)</span>
                        </label>
                    @else
                        <input type="hidden" name="is_active" value="1">
                    @endunless

                    {{-- Extra permissions: shown to Admins only; not for Admin/Customer roles --}}
                    @if (count($permissions) && ! in_array($user->role, [App\Enums\Role::Admin, App\Enums\Role::Client], true))
                        @php($fromRole = $rolePermissions[$user->role?->value] ?? [])
                        <fieldset class="border border-gray-200 rounded-md p-4">
                            <legend class="px-1 text-sm font-medium text-gray-700">Extra permissions (on top of the role)</legend>
                            <div class="grid sm:grid-cols-2 gap-2 mt-1">
                                @foreach ($permissions as $p)
                                    @php($viaRole = in_array($p->value, $fromRole, true))
                                    <label class="flex items-start gap-2 text-sm {{ $viaRole ? 'text-gray-400' : 'text-gray-700' }}">
                                        <input type="checkbox" name="extra_permissions[]" value="{{ $p->value }}" class="mt-0.5 rounded border-gray-300"
                                            @checked($viaRole || in_array($p->value, old('extra_permissions', $extra), true)) @disabled($viaRole)>
                                        <span>{{ $p->label() }}@if ($viaRole) <span class="text-xs">(from role)</span>@endif</span>
                                    </label>
                                @endforeach
                            </div>
                            <p class="text-xs text-gray-500 mt-2">If you changed the role, Update first and then check here. Role permissions are on the <a href="{{ route('admin.roles.index') }}" class="underline">Roles</a> page.</p>
                        </fieldset>
                    @endif

                    <div class="flex items-center gap-3">
                        <x-primary-button>{{ $user->exists ? 'Update' : 'Create' }}</x-primary-button>
                        <a href="{{ route('admin.users.index') }}" class="text-sm text-gray-600 hover:underline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
