<?php

namespace Tests\Feature;

use App\Enums\CalendarStatus;
use App\Enums\Role;
use App\Models\Calendar;
use App\Models\Client;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_packages_are_there(): void
    {
        $this->assertSame(['Starter', 'Growth', 'Scale', 'Premium Growth'], Package::orderBy('price')->pluck('name')->all());
        $this->assertSame(3, Package::firstWhere('name', 'Premium Growth')->min_months);
    }

    public function test_only_admin_manages_packages(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $manager = User::factory()->create(['role' => Role::Manager]);

        $this->actingAs($manager)->get(route('admin.packages.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.packages.index'))->assertOk()->assertSee('₹7,500');

        $this->actingAs($admin)->post(route('admin.packages.store'), [
            'name' => 'Custom', 'price' => 20000, 'platforms' => ['instagram'], 'static_posts' => 5,
            'carousel_posts' => 0, 'reels' => 5, 'services' => "Captions\n\nReport\n", 'is_active' => 1,
        ])->assertRedirect(route('admin.packages.index'));
        $this->assertSame(['Captions', 'Report'], Package::firstWhere('name', 'Custom')->services);
    }

    public function test_price_hidden_from_smm_but_package_shown(): void
    {
        $package = Package::firstWhere('name', 'Growth');
        $client = Client::create(['name' => 'Acme', 'package_id' => $package->id]);
        $smm = User::factory()->create(['role' => Role::SocialMediaManager]);
        $calendar = Calendar::create(['client_id' => $client->id, 'month' => '2026-11-01', 'created_by' => $smm->id, 'status' => CalendarStatus::Draft]);
        $calendar->items()->create(['post_date' => '2026-11-03', 'post_types' => ['instagram_reel', 'facebook_reel']]);

        $this->actingAs($smm)->get(route('calendars.show', $calendar))
            ->assertOk()
            ->assertSee('Growth package')
            ->assertSee('1/6')
            ->assertSee('Content Strategy')
            ->assertDontSee('15,000');
    }

    public function test_manager_sees_price_on_clients(): void
    {
        Client::create(['name' => 'Acme', 'package_id' => Package::firstWhere('name', 'Scale')->id]);
        $manager = User::factory()->create(['role' => Role::Manager]);

        $this->actingAs($manager)->get(route('clients.index'))->assertOk()->assertSee('Scale')->assertSee('₹35,000');
    }

    public function test_customer_sees_own_package_and_price(): void
    {
        $client = Client::create(['name' => 'Acme', 'package_id' => Package::firstWhere('name', 'Starter')->id]);
        $customer = User::factory()->create(['role' => Role::Client, 'client_id' => $client->id]);

        $this->actingAs($customer)->get(route('dashboard'))
            ->assertOk()->assertSee('Your package')->assertSee('Starter')->assertSee('₹7,500')->assertSee('0/4');
    }
}
