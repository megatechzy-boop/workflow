<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->with('client')
            ->when($request->role, fn ($q, $role) => $q->where('role', $role))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%")))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', ['users' => $users, 'roles' => Role::cases()]);
    }

    public function create(Request $request): View
    {
        return $this->form($request, new User(['is_active' => true]));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['email_verified_at'] = now();

        $user = User::create($data);
        $this->syncExtraPermissions($request, $user);

        return redirect()->route('admin.users.index')->with('status', 'User created.');
    }

    public function edit(Request $request, User $user): View
    {
        $this->guardAdminAccount($request, $user);

        return $this->form($request, $user);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->guardAdminAccount($request, $user);
        $data = $this->validated($request, $user);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        // Nobody can change their own role/active status (prevents lock-out)
        if ($user->is($request->user())) {
            unset($data['role'], $data['is_active']);
        }

        $user->update($data);
        $this->syncExtraPermissions($request, $user);

        return redirect()->route('admin.users.index')->with('status', 'User updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->guardAdminAccount($request, $user);

        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot delete yourself.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('status', 'User deleted.');
    }

    /** A non-admin with the manage-users permission cannot touch Admin accounts */
    private function guardAdminAccount(Request $request, User $user): void
    {
        abort_if($user->isAdmin() && ! $request->user()->isAdmin(), 403, 'Only an Admin can change an Admin account.');
    }

    private function form(Request $request, User $user): View
    {
        $actorIsAdmin = $request->user()->isAdmin();

        return view('admin.users.form', [
            'user' => $user,
            // Non-admins cannot make anyone an Admin
            'roles' => array_values(array_filter(Role::cases(), fn ($r) => $actorIsAdmin || $r !== Role::Admin)),
            'clients' => Client::orderBy('name')->get(),
            'permissions' => $actorIsAdmin ? Permission::cases() : [],
            'rolePermissions' => DB::table('role_permissions')->get()->groupBy('role')->map->pluck('permission')->map->all(),
            'extra' => $user->exists ? $user->extraPermissions() : [],
        ]);
    }

    /** Only Admins grant extra permissions; nothing is stored for Admin/Customer */
    private function syncExtraPermissions(Request $request, User $user): void
    {
        if (! $request->user()->isAdmin()) {
            return;
        }

        $selected = in_array($user->role, [Role::Admin, Role::Client], true) ? [] : array_unique($request->input('extra_permissions', []));

        DB::transaction(function () use ($user, $selected) {
            DB::table('user_permissions')->where('user_id', $user->id)->delete();
            DB::table('user_permissions')->insert(array_map(fn ($p) => ['user_id' => $user->id, 'permission' => $p], $selected));
        });
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'role' => ['required', Rule::enum(Role::class), Rule::notIn($request->user()->isAdmin() ? [] : [Role::Admin->value])],
            'client_id' => ['nullable', 'required_if:role,client', 'exists:clients,id'],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Rules\Password::defaults()],
            'extra_permissions' => ['array'],
            'extra_permissions.*' => [Rule::enum(Permission::class)],
        ], [
            'client_id.required_if' => 'Please choose a brand for the Customer role.',
            'role.not_in' => 'Only an Admin can give the Admin role.',
        ]);
        unset($data['extra_permissions']);
        $data['is_active'] = $request->boolean('is_active');
        // client_id only matters for the Customer role
        if ($data['role'] !== Role::Client->value) {
            $data['client_id'] = null;
        }

        return $data;
    }
}
