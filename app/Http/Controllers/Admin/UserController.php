<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->when($request->role, fn ($q, $role) => $q->where('role', $role))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%")))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', ['users' => $users, 'roles' => Role::cases()]);
    }

    public function create(): View
    {
        return view('admin.users.form', ['user' => new User(['is_active' => true]), 'roles' => Role::cases()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['email_verified_at'] = now();

        User::create($data);

        return redirect()->route('admin.users.index')->with('status', 'User create ho gaya.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', ['user' => $user, 'roles' => Role::cases()]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        // Admin khud ka role/active status na badal sake (lock-out se bachne ke liye)
        if ($user->is($request->user())) {
            unset($data['role'], $data['is_active']);
        }

        $user->update($data);

        return redirect()->route('admin.users.index')->with('status', 'User update ho gaya.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'Aap khud ko delete nahi kar sakte.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('status', 'User delete ho gaya.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'role' => ['required', Rule::enum(Role::class)],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Rules\Password::defaults()],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
