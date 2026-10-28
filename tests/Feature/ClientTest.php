<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_manager_can_manage_clients(): void
    {
        foreach ([Role::Admin, Role::Manager] as $i => $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get('/clients')->assertOk();
            $this->actingAs($user)->post('/clients', ['name' => "Brand $i", 'is_active' => '1'])
                ->assertRedirect('/clients');
        }

        $this->assertSame(2, Client::count());
    }

    public function test_other_roles_cannot_open_clients(): void
    {
        foreach ([Role::SocialMediaManager, Role::GraphicDesigner, Role::Client] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get('/clients')->assertForbidden();
        }
    }

    public function test_client_role_user_must_be_linked_to_a_client(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $client = Client::create(['name' => 'Acme']);
        $payload = [
            'name' => 'Acme Owner', 'email' => 'owner@acme.test', 'role' => 'client',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ];

        $this->actingAs($admin)->post('/admin/users', $payload)->assertSessionHasErrors('client_id');

        $this->actingAs($admin)->post('/admin/users', $payload + ['client_id' => $client->id])
            ->assertRedirect('/admin/users');
        $this->assertTrue($client->users()->where('email', 'owner@acme.test')->exists());
    }

    public function test_client_id_is_cleared_for_non_client_roles(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $client = Client::create(['name' => 'Acme']);

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Staff', 'email' => 'staff@acme.test', 'role' => 'graphic_designer', 'client_id' => $client->id,
            'password' => 'password123', 'password_confirmation' => 'password123',
        ]);

        $this->assertNull(User::where('email', 'staff@acme.test')->value('client_id'));
    }
}
