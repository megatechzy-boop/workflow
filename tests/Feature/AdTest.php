<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Client;
use App\Models\MetaAd;
use App\Models\MetaAdAccount;
use App\Models\MetaConnection;
use App\Models\MetaPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdTest extends TestCase
{
    use RefreshDatabase;

    private User $smm;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->travelTo('2026-11-01 10:00:00');
        $page = MetaPage::create(['page_id' => '111', 'name' => 'Acme Yoga', 'access_token' => 'page-token', 'ig_user_id' => '999']);
        $account = MetaAdAccount::create(['account_id' => 'act_55', 'name' => 'Acme Ads', 'currency' => 'INR']);
        $this->client = Client::create(['name' => 'Acme', 'meta_page_id' => $page->id, 'meta_ad_account_id' => $account->id]);
        MetaConnection::create(['user_token' => 'user-token']);
        $this->smm = User::factory()->create(['role' => Role::SocialMediaManager]);
    }

    private function form(array $override = []): array
    {
        return $override + [
            'client_id' => $this->client->id, 'name' => 'Diwali WhatsApp', 'goal' => 'whatsapp', 'daily_budget' => 300,
            'start_on' => '2026-11-01', 'end_on' => '2026-11-08', 'city' => 'Solapur', 'radius_km' => 15, 'age_min' => 22, 'age_max' => 55,
            'gender' => 'female', 'interests' => 'Yoga', 'primary_text' => 'Join our 14-day spine reset', 'headline' => 'Book now',
            'image' => UploadedFile::fake()->image('ad.jpg', 1080, 1080),
        ];
    }

    public function test_ad_is_created_paused_then_started(): void
    {
        Http::fake([
            '*/act_55/adimages' => Http::response(['images' => ['ad.jpg' => ['hash' => 'h1']]]),
            '*type=adgeolocation*' => Http::response(['data' => [['key' => '1035921']]]),
            '*type=adinterest*' => Http::response(['data' => [['id' => '600', 'name' => 'Yoga']]]),
            '*/act_55/campaigns' => Http::response(['id' => 'c1']),
            '*/act_55/adsets' => Http::response(['id' => 's1']),
            '*/act_55/adcreatives' => Http::response(['id' => 'cr1']),
            '*/act_55/ads' => Http::response(['id' => 'ad1']),
            '*/c1' => Http::response(['success' => true]), '*/s1' => Http::response(['success' => true]), '*/ad1' => Http::response(['success' => true]),
        ]);

        $this->actingAs($this->smm)->get(route('ads.create'))->assertOk()->assertSee('WhatsApp messages');
        $this->actingAs($this->smm)->post(route('ads.store'), $this->form())->assertSessionHas('status', 'Ad created (paused). Check it, then press Start.');

        $ad = MetaAd::sole();
        $this->assertSame(['paused', 'c1', 's1', 'ad1'], [$ad->status, $ad->campaign_id, $ad->adset_id, $ad->ad_id]);
        Http::assertSent(function (Request $r) {
            if (! str_ends_with($r->url(), '/act_55/adsets')) {
                return false;
            }
            $t = json_decode($r['targeting'], true);

            return $r['status'] === 'PAUSED' && $r['destination_type'] === 'WHATSAPP' && $r['optimization_goal'] === 'CONVERSATIONS'
                && $t['genders'] === [2] && $t['flexible_spec'][0]['interests'][0]['id'] === '600' && $t['geo_locations']['cities'][0]['radius'] === 15;
        });
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/act_55/adcreatives')
            && json_decode($r['object_story_spec'], true)['link_data']['call_to_action']['type'] === 'WHATSAPP_MESSAGE');

        $this->actingAs($this->smm)->post(route('ads.status', $ad), ['status' => 'active'])->assertSessionHas('status', 'Ad is live. Spending starts now.');
        $this->assertSame('active', $ad->fresh()->status);
    }

    public function test_website_goal_needs_a_link_and_meta_errors_are_shown(): void
    {
        $this->actingAs($this->smm)->post(route('ads.store'), $this->form(['goal' => 'website']))->assertSessionHasErrors('url');

        Http::fake(['*/act_55/adimages' => Http::response(['error' => ['message' => 'Invalid image']], 400)]);
        $this->actingAs($this->smm)->post(route('ads.store'), $this->form(['goal' => 'website', 'url' => 'https://acme.in']));
        $this->assertSame(['failed', 'Invalid image'], [MetaAd::sole()->status, MetaAd::sole()->error]);
    }

    public function test_only_publishers_and_their_brands(): void
    {
        $designer = User::factory()->create(['role' => Role::GraphicDesigner]);
        $this->actingAs($designer)->get(route('ads.index'))->assertForbidden();

        $other = Client::create(['name' => 'Zen', 'meta_page_id' => $this->client->meta_page_id, 'meta_ad_account_id' => $this->client->meta_ad_account_id]);
        $this->smm->brands()->sync([$other->id]);
        $this->actingAs($this->smm)->post(route('ads.store'), $this->form())->assertSessionHasErrors('client_id');
    }
}
