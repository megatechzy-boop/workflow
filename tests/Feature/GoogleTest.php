<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Client;
use App\Models\GbpLocation;
use App\Models\GoogleAccount;
use App\Models\User;
use App\Support\GoogleBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google.client_id' => 'cid', 'services.google.client_secret' => 'secret']);
        $this->admin = User::factory()->create(['role' => Role::Admin]);
    }

    private function fakeGoogle(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-1', 'refresh_token' => 'refresh-1', 'expires_in' => 3599]),
            'mybusinessaccountmanagement.googleapis.com/*' => Http::response(['accounts' => [['name' => 'accounts/11', 'accountName' => 'Mega Techzy']]]),
            'mybusinessbusinessinformation.googleapis.com/*' => Http::sequence()
                ->push(['locations' => [['name' => 'locations/21', 'title' => 'Acme Yoga', 'storefrontAddress' => ['locality' => 'Pune'], 'phoneNumbers' => ['primaryPhone' => '020 1234']]], 'nextPageToken' => 'p2'])
                ->push(['locations' => [['name' => 'locations/22', 'title' => 'Acme Dental']]]),
        ]);
    }

    public function test_admin_connects_google_and_locations_are_saved(): void
    {
        $this->fakeGoogle();

        $redirect = $this->actingAs($this->admin)->get(route('admin.google.connect'))->headers->get('Location');
        $this->assertStringContainsString(urlencode(GoogleBusiness::SCOPE), $redirect);
        $this->assertStringContainsString('access_type=offline', $redirect);
        $this->assertStringContainsString(urlencode('/google/callback'), $redirect);
        parse_str(parse_url($redirect, PHP_URL_QUERY), $query);

        $this->actingAs($this->admin)->get(route('google.callback', ['code' => 'abc', 'state' => $query['state']]))
            ->assertRedirect(route('admin.google.index'))
            ->assertSessionHas('status', '2 location(s) found. Link each one to its client below.');

        $this->assertSame('refresh-1', GoogleAccount::sole()->refresh_token);
        $this->assertNotSame('refresh-1', DB::table('google_accounts')->value('refresh_token')); // stored encrypted
        $location = GbpLocation::where('location_name', 'locations/21')->sole();
        $this->assertSame(['accounts/11', 'Pune', '020 1234'], [$location->account_name, $location->city, $location->phone]);
        $this->assertSame('accounts/11/locations/21', $location->v4Path());
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'pageToken=p2'));
        $this->actingAs($this->admin)->get(route('admin.google.index'))->assertOk()->assertSee('Acme Dental');
    }

    public function test_callback_rejects_wrong_state_and_non_admins(): void
    {
        $this->actingAs($this->admin)->get(route('google.callback', ['code' => 'abc', 'state' => 'forged']))
            ->assertSessionHas('status', 'Google connection was cancelled.');
        $this->assertSame(0, GoogleAccount::count());

        $manager = User::factory()->create(['role' => Role::Manager]);
        $this->actingAs($manager)->get(route('admin.google.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('google.callback', ['code' => 'abc']))->assertForbidden();
    }

    public function test_expired_token_is_renewed_with_the_refresh_token(): void
    {
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-2', 'expires_in' => 3599])]);
        GoogleAccount::create(['access_token' => 'old', 'refresh_token' => 'refresh-1', 'expires_at' => now()->subMinute()]);

        $this->assertSame('access-2', GoogleBusiness::token());
        $this->assertSame('access-2', GoogleBusiness::token()); // cached until it expires
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => $r['grant_type'] === 'refresh_token' && $r['refresh_token'] === 'refresh-1');
        $this->assertTrue(GoogleAccount::sole()->expires_at->isFuture());
    }

    public function test_refresh_keeps_client_links(): void
    {
        $this->fakeGoogle();
        GoogleAccount::create(['access_token' => 'a', 'refresh_token' => 'r', 'expires_at' => now()->addHour()]);
        $client = Client::create(['name' => 'Acme']);
        GbpLocation::create(['client_id' => $client->id, 'account_name' => 'accounts/11', 'location_name' => 'locations/21', 'title' => 'Old name']);

        $this->actingAs($this->admin)->post(route('admin.google.refresh'))->assertSessionHas('status');

        $location = GbpLocation::where('location_name', 'locations/21')->sole();
        $this->assertSame([$client->id, 'Acme Yoga'], [$location->client_id, $location->title]);
        $this->assertSame(2, GbpLocation::count());
    }

    public function test_location_under_two_accounts_comes_in_once_and_refresh_keeps_its_account(): void
    {
        Http::fake([
            'mybusinessaccountmanagement.googleapis.com/*' => Http::response(['accounts' => [['name' => 'accounts/11'], ['name' => 'accounts/77']]]),
            'mybusinessbusinessinformation.googleapis.com/*' => Http::response(['locations' => [['name' => 'locations/21', 'title' => 'Acme Yoga']]]),
        ]);
        GoogleAccount::create(['access_token' => 'a', 'refresh_token' => 'r', 'expires_at' => now()->addHour()]);

        $this->assertCount(1, GoogleBusiness::locations('a'));

        // a reply already proved accounts/77 works: refresh must not switch it back
        GbpLocation::create(['account_name' => 'accounts/77', 'location_name' => 'locations/21', 'title' => 'Old']);
        $this->actingAs($this->admin)->post(route('admin.google.refresh'));
        $this->assertSame(['accounts/77', 'Acme Yoga'], [GbpLocation::sole()->account_name, GbpLocation::sole()->title]);
    }

    public function test_admin_links_a_location_to_one_client_only(): void
    {
        $client = Client::create(['name' => 'Acme']);
        $one = GbpLocation::create(['account_name' => 'accounts/11', 'location_name' => 'locations/21', 'title' => 'Acme Yoga']);
        $two = GbpLocation::create(['account_name' => 'accounts/11', 'location_name' => 'locations/22', 'title' => 'Acme Dental']);

        $this->actingAs($this->admin)->put(route('admin.google.link', $one), ['client_id' => $client->id])
            ->assertSessionHas('status', 'Acme Yoga linked to Acme.');
        $this->assertSame($one->id, $client->gbpLocation->id);

        $this->actingAs($this->admin)->put(route('admin.google.link', $two), ['client_id' => $client->id])->assertSessionHasErrors('client_id');

        $this->actingAs($this->admin)->put(route('admin.google.link', $one), ['client_id' => ''])->assertSessionHas('status', 'Acme Yoga unlinked.');
        $this->assertNull($one->fresh()->client_id);
    }
}
