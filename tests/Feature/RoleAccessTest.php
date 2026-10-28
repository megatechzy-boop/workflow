<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_user_management(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);

        $this->actingAs($admin)->get('/admin/users')->assertOk();
    }

    public function test_non_admin_roles_are_blocked_from_user_management(): void
    {
        foreach ([Role::Manager, Role::SocialMediaManager, Role::GraphicDesigner, Role::Client] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get('/admin/users')->assertForbidden();
        }
    }

    public function test_admin_can_create_user_with_role(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'New Approver',
            'email' => 'new@workflow.test',
            'role' => 'social_media_manager',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'is_active' => '1',
        ])->assertRedirect('/admin/users');

        $this->assertSame(Role::SocialMediaManager, User::where('email', 'new@workflow.test')->first()->role);
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->create(['email' => 'off@workflow.test', 'is_active' => false]);

        $this->post('/login', ['email' => 'off@workflow.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_registration_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_admin_cannot_demote_deactivate_or_delete_self(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);

        $this->actingAs($admin)->put("/admin/users/{$admin->id}", [
            'name' => 'Admin', 'email' => $admin->email, 'role' => 'client', 'is_active' => '0',
        ]);
        $this->actingAs($admin)->delete("/admin/users/{$admin->id}");

        $admin->refresh();
        $this->assertSame(Role::Admin, $admin->role);
        $this->assertTrue($admin->is_active);
    }

    public function test_deactivated_user_is_logged_out_on_next_request(): void
    {
        $user = User::factory()->create(['role' => Role::GraphicDesigner]);
        $this->actingAs($user)->get('/dashboard')->assertOk();

        $user->update(['is_active' => false]);

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }
}
