<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Client;
use App\Models\MetaAd;
use App\Models\MetaAdAccount;
use App\Models\MetaAudience;
use App\Models\MetaConnection;
use App\Models\MetaLead;
use App\Models\MetaPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Meta ads > Audiences, Pixel and A/B tests */
class AdToolsTest extends TestCase
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

    public function test_page_engager_and_lookalike_audiences(): void
    {
        Http::fake(['*/act_55/customaudiences' => Http::sequence()->push(['id' => 'aud1'])->push(['id' => 'aud2'])]);

        $this->actingAs($this->smm)->post(route('ads.audiences.store'), ['client_id' => $this->client->id, 'name' => 'Engagers 90d', 'type' => 'page', 'days' => 90])
            ->assertSessionHas('status', 'Audience ready. Meta fills it within a few hours; pick it in New ad.');
        $source = MetaAudience::sole();
        $this->assertSame(['aud1', 'ready'], [$source->audience_id, $source->status]);
        Http::assertSent(function (Request $r) {
            $rule = json_decode($r['rule'] ?? '{}', true)['inclusions']['rules'][0] ?? null;

            return $rule && $rule['event_sources'][0] === ['id' => '111', 'type' => 'page'] && $rule['retention_seconds'] === 90 * 86400;
        });

        $this->actingAs($this->smm)->post(route('ads.audiences.store'), ['client_id' => $this->client->id, 'name' => 'Lookalike 1%', 'type' => 'lookalike', 'source_id' => $source->id, 'lookalike_percent' => 1]);
        Http::assertSent(fn (Request $r) => ($r['subtype'] ?? null) === 'LOOKALIKE' && $r['origin_audience_id'] === 'aud1'
            && json_decode($r['lookalike_spec'], true)['ratio'] === 0.01);

        $this->actingAs($this->smm)->get(route('ads.audiences'))->assertOk()->assertSee('Engagers 90d')->assertSee('1% of India');
    }

    public function test_customer_list_is_hashed_before_sending(): void
    {
        MetaLead::create(['meta_page_id' => $this->client->meta_page_id, 'client_id' => $this->client->id, 'lead_id' => 'l1', 'phone' => '+91 98765 43210', 'fields' => []]);
        Http::fake(['*/act_55/customaudiences' => Http::response(['id' => 'aud1']), '*/aud1/users' => Http::response(['num_received' => 2])]);

        $this->actingAs($this->smm)->post(route('ads.audiences.store'), ['client_id' => $this->client->id, 'name' => 'Customers', 'type' => 'list',
            'contacts' => "Neha@Example.com\n98765 43210", 'with_leads' => 1]);

        $this->assertSame(2, MetaAudience::sole()->list_count); // the lead's phone is the same number
        Http::assertSent(function (Request $r) {
            if (! str_ends_with($r->url(), '/aud1/users')) {
                return false;
            }
            $payload = json_decode($r['payload'], true);

            return $payload['schema'] === ['EMAIL', 'PHONE'] && in_array([hash('sha256', 'neha@example.com'), ''], $payload['data'], true)
                && in_array(['', hash('sha256', '919876543210')], $payload['data'], true) && ! str_contains($r->body(), '98765');
        });
    }

    public function test_pixel_setup_and_conversion(): void
    {
        Http::fake([
            '*/act_55/adspixels*' => Http::sequence()->push(['data' => []])->push(['id' => 'px1'])->push(['data' => [['id' => 'px1', 'name' => 'Acme website']]]),
            '*/px1/stats*' => Http::response(['data' => [['data' => [['value' => 'PageView', 'count' => 120], ['value' => 'Lead', 'count' => 4]]]]]),
            '*/act_55/customconversions*' => Http::response(['data' => []]),
        ]);

        $this->actingAs($this->smm)->get(route('ads.pixel'))->assertOk()->assertSee('Set up Acme');
        $this->actingAs($this->smm)->post(route('ads.pixel.store', $this->client), ['name' => 'Acme website']);
        $this->assertSame('px1', $this->client->fresh()->meta_pixel_id);

        $this->actingAs($this->smm)->get(route('ads.pixel'))->assertOk()->assertSee("fbq('init', 'px1')")->assertSee('PageView')->assertSee('120');

        $this->actingAs($this->smm)->post(route('ads.pixel.conversion', $this->client), ['name' => 'Enquiry sent', 'url_contains' => 'thank-you', 'event' => 'LEAD'])
            ->assertSessionHas('status', 'Conversion added. It counts from the next visit to that page.');
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/customconversions')
            && $r['pixel_id'] === 'px1' && $r['custom_event_type'] === 'LEAD');

        $this->actingAs(User::factory()->create(['role' => Role::GraphicDesigner]))->get(route('ads.pixel'))->assertForbidden();
    }

    public function test_ab_test_makes_two_ad_sets_and_a_split_test(): void
    {
        Http::fake([
            '*/act_55/adimages' => Http::response(['images' => ['a.jpg' => ['hash' => 'h1']]]),
            '*/act_55/campaigns' => Http::response(['id' => 'c1']),
            '*/act_55/adsets' => Http::sequence()->push(['id' => 's1'])->push(['id' => 's2']),
            '*/act_55/adcreatives' => Http::sequence()->push(['id' => 'cr1'])->push(['id' => 'cr2']),
            '*/act_55/ads' => Http::sequence()->push(['id' => 'ad1'])->push(['id' => 'ad2']),
            '*/act_55?*' => Http::response(['business' => ['id' => 'biz1']]),
            '*/biz1/ad_studies' => Http::response(['id' => 'st1']),
        ]);
        $form = ['client_id' => $this->client->id, 'name' => 'Diwali', 'goal' => 'whatsapp', 'daily_budget' => 400, 'start_on' => '2026-11-01', 'end_on' => '2026-11-08',
            'age_min' => 22, 'age_max' => 55, 'gender' => 'all', 'primary_text' => 'Join now', 'image' => UploadedFile::fake()->image('a.jpg', 1080, 1080)];

        $this->actingAs($this->smm)->post(route('ads.store'), $form + ['ab_test' => 1])->assertSessionHasErrors('image_b');
        $this->actingAs($this->smm)->post(route('ads.store'), $form + ['ab_test' => 1, 'primary_text_b' => 'Only 10 seats left']);

        $ad = MetaAd::sole();
        $this->assertSame(['ad1', 'ad2', 's2', 'st1', 'paused'], [$ad->ad_id, $ad->ad_b_id, $ad->adset_b_id, $ad->study_id, $ad->status]);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/act_55/adsets') && $r['daily_budget'] === 20000);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/act_55/adcreatives')
            && json_decode($r['object_story_spec'], true)['link_data']['message'] === 'Only 10 seats left');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/biz1/ad_studies') && $r['type'] === 'SPLIT_TEST'
            && array_column(json_decode($r['cells'], true), 'adsets') === [['s1'], ['s2']]);

        Http::fake(['*/ad1/insights*' => Http::response(['data' => [['spend' => '800', 'actions' => [['action_type' => 'onsite_conversion.messaging_conversation_started_7d', 'value' => '10']]]]]),
            '*/ad2/insights*' => Http::response(['data' => [['spend' => '800', 'actions' => [['action_type' => 'onsite_conversion.messaging_conversation_started_7d', 'value' => '20']]]]])]);
        $this->travelTo('2026-11-04 10:00:00');
        $this->actingAs($this->smm)->get(route('ads.index'))->assertOk()->assertSeeInOrder(['Version A', '₹80.00 each', 'Version B', 'Leading', '₹40.00 each']);
    }

    public function test_ad_uses_saved_audience(): void
    {
        $audience = MetaAudience::create(['client_id' => $this->client->id, 'meta_ad_account_id' => $this->client->meta_ad_account_id, 'name' => 'Engagers', 'type' => 'page', 'days' => 90, 'audience_id' => 'aud9']);
        Http::fake([
            '*/act_55/adimages' => Http::response(['images' => ['a.jpg' => ['hash' => 'h1']]]),
            '*/act_55/campaigns' => Http::response(['id' => 'c1']), '*/act_55/adsets' => Http::response(['id' => 's1']),
            '*/act_55/adcreatives' => Http::response(['id' => 'cr1']), '*/act_55/ads' => Http::response(['id' => 'ad1']),
        ]);

        $this->actingAs($this->smm)->get(route('ads.create'))->assertSee('Engagers');
        $this->actingAs($this->smm)->post(route('ads.store'), ['client_id' => $this->client->id, 'name' => 'Retarget', 'goal' => 'whatsapp', 'daily_budget' => 300,
            'start_on' => '2026-11-01', 'end_on' => '2026-11-08', 'age_min' => 22, 'age_max' => 55, 'gender' => 'all', 'primary_text' => 'Come back',
            'image' => UploadedFile::fake()->image('a.jpg', 1080, 1080), 'meta_audience_id' => $audience->id]);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/act_55/adsets')
            && json_decode($r['targeting'], true)['custom_audiences'] === [['id' => 'aud9']]);
    }
}
