<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_match_old_behaviour(): void
    {
        $this->assertTrue(User::factory()->create(['role' => Role::SocialMediaManager])->hasPermission(Permission::ManageCalendars));
        $this->assertTrue(User::factory()->create(['role' => Role::Manager])->hasPermission(Permission::ManageClients));
        $this->assertFalse(User::factory()->create(['role' => Role::GraphicDesigner])->hasPermission(Permission::ManageCalendars));
        $this->assertFalse(User::factory()->create(['role' => Role::Client])->hasPermission(Permission::ManageCalendars));
    }

    public function test_admin_changes_role_matrix_and_it_applies(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);

        // Designer ko "Clients manage" do, SMM se calendar le lo
        $this->actingAs($admin)->put('/admin/roles', ['grant' => [
            'graphic_designer' => ['manage_clients'],
            'social_media_manager' => ['assign_tasks'],
        ]])->assertSessionHasNoErrors();

        $designer = User::factory()->create(['role' => Role::GraphicDesigner]);
        $this->actingAs($designer)->get('/clients')->assertOk();

        $smm = User::factory()->create(['role' => Role::SocialMediaManager]);
        $this->actingAs($smm)->get('/calendars/create')->assertForbidden();
    }

    public function test_only_admin_opens_roles_page(): void
    {
        $manager = User::factory()->create(['role' => Role::Manager]);

        $this->actingAs($manager)->get('/admin/roles')->assertForbidden();
        $this->actingAs($manager)->put('/admin/roles', ['grant' => ['manager' => ['manage_users']]])->assertForbidden();
    }

    public function test_extra_permission_for_one_member(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $designer = User::factory()->create(['role' => Role::GraphicDesigner]);

        $this->actingAs($admin)->put("/admin/users/{$designer->id}", [
            'name' => $designer->name, 'email' => $designer->email, 'role' => 'graphic_designer', 'is_active' => '1',
            'extra_permissions' => ['manage_calendars'],
        ])->assertSessionHasNoErrors();

        $this->actingAs($designer->fresh())->get('/calendars/create')->assertOk();
        // Doosre designer ko nahi mila
        $this->actingAs(User::factory()->create(['role' => Role::GraphicDesigner]))->get('/calendars/create')->assertForbidden();
    }

    public function test_user_manager_cannot_create_or_touch_admins(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $hr = User::factory()->create(['role' => Role::Manager]);
        $this->actingAs($admin)->put("/admin/users/{$hr->id}", [
            'name' => $hr->name, 'email' => $hr->email, 'role' => 'manager', 'is_active' => '1', 'extra_permissions' => ['manage_users'],
        ]);
        $hr = $hr->fresh();

        $this->actingAs($hr)->get('/admin/users')->assertOk();
        $this->actingAs($hr)->post('/admin/users', [
            'name' => 'Sneaky', 'email' => 'sneaky@x.test', 'role' => 'admin',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('role');
        $this->actingAs($hr)->get("/admin/users/{$admin->id}/edit")->assertForbidden();
        $this->actingAs($hr)->delete("/admin/users/{$admin->id}")->assertForbidden();

        // Non-admin extra permissions nahi de sakta
        $designer = User::factory()->create(['role' => Role::GraphicDesigner]);
        $this->actingAs($hr)->put("/admin/users/{$designer->id}", [
            'name' => $designer->name, 'email' => $designer->email, 'role' => 'graphic_designer', 'is_active' => '1',
            'extra_permissions' => ['manage_users'],
        ]);
        $this->assertSame([], $designer->extraPermissions());
    }
}
