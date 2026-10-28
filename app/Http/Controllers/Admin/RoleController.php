<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Admin > Roles: permission matrix per role */
class RoleController extends Controller
{
    public function index(): View
    {
        return view('admin.roles.index', [
            'roles' => $this->editableRoles(),
            'permissions' => Permission::cases(),
            'granted' => DB::table('role_permissions')->get()->groupBy('role')->map->pluck('permission')->map->all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $roleValues = array_map(fn ($r) => $r->value, $this->editableRoles());
        $data = $request->validate([
            'grant' => ['array'],
            'grant.*' => ['array'],
            'grant.*.*' => [Rule::enum(Permission::class)],
        ]);

        DB::transaction(function () use ($data, $roleValues) {
            DB::table('role_permissions')->whereIn('role', $roleValues)->delete();
            $rows = [];
            foreach ($roleValues as $role) {
                foreach (array_unique($data['grant'][$role] ?? []) as $permission) {
                    $rows[] = ['role' => $role, 'permission' => $permission];
                }
            }
            DB::table('role_permissions')->insert($rows);
        });

        return back()->with('status', 'Role permissions saved.');
    }

    /** Admin always has all; Customers never get internal permissions */
    private function editableRoles(): array
    {
        return [Role::Manager, Role::SocialMediaManager, Role::GraphicDesigner, Role::VideoEditor];
    }
}
