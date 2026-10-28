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

        return redirect()->route('admin.users.index')->with('status', 'User create ho gaya.');
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

        // Koi khud ka role/active status na badal sake (lock-out se bachne ke liye)
        if ($user->is($request->user())) {
            unset($data['role'], $data['is_active']);
        }

        $user->update($data);
        $this->syncExtraPermissions($request, $user);

        return redirect()->route('admin.users.index')->with('status', 'User update ho gaya.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->guardAdminAccount($request, $user);

        if ($user->is($request->user())) {
            return back()->with('error', 'Aap khud ko delete nahi kar sakte.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('status', 'User delete ho gaya.');
    }

    /** "Users manage" permission wala non-admin, Admin accounts ko chhoo nahi sakta */
    private function guardAdminAccount(Request $request, User $user): void
    {
        abort_if($user->isAdmin() && ! $request->user()->isAdmin(), 403, 'Admin account sirf Admin badal sakta hai.');
    }

    private function form(Request $request, User $user): View
    {
        $actorIsAdmin = $request->user()->isAdmin();

        return view('admin.users.form', [
            'user' => $user,
            // Non-admin kisi ko Admin nahi bana sakta
            'roles' => array_values(array_filter(Role::cases(), fn ($r) => $actorIsAdmin || $r !== Role::Admin)),
            'clients' => Client::orderBy('name')->get(),
            'permissions' => $actorIsAdmin ? Permission::cases() : [],
            'rolePermissions' => DB::table('role_permissions')->get()->groupBy('role')->map->pluck('permission')->map->all(),
            'extra' => $user->exists ? $user->extraPermissions() : [],
        ]);
    }

    /** Extra permissions sirf Admin de sakta hai; Admin/Customer ke liye kuch save nahi hota */
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
            'client_id.required_if' => 'Customer role ke liye brand chunna zaroori hai.',
            'role.not_in' => 'Admin role sirf Admin de sakta hai.',
        ]);
        unset($data['extra_permissions']);
        $data['is_active'] = $request->boolean('is_active');
        // client_id sirf Customer role par matlab rakhta hai
        if ($data['role'] !== Role::Client->value) {
            $data['client_id'] = null;
        }

        return $data;
    }
}
