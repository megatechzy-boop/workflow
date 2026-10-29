<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class PushGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_role_gets_the_notification_gate(): void
    {
        Config::set('webpush.vapid.public_key', 'test-public-key');

        foreach (Role::cases() as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('dashboard'))
                ->assertOk()
                ->assertSee('id="push-gate"', false)
                ->assertSee('Turn on notifications');
        }
    }

    public function test_no_gate_without_vapid_key(): void
    {
        Config::set('webpush.vapid.public_key', null);
        $user = User::factory()->create(['role' => Role::Admin]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('id="push-gate"', false);
    }

    public function test_no_gate_on_login_page(): void
    {
        Config::set('webpush.vapid.public_key', 'test-public-key');

        $this->get(route('login'))->assertOk()->assertDontSee('id="push-gate"', false);
    }
}
