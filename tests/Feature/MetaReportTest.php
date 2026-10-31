<?php

namespace Tests\Feature;

use App\Enums\CalendarStatus;
use App\Enums\ContentStatus;
use App\Enums\Role;
use App\Models\Calendar;
use App\Models\Client;
use App\Models\Content;
use App\Models\MetaAd;
use App\Models\MetaAdAccount;
use App\Models\MetaConnection;
use App\Models\MetaLead;
use App\Models\MetaPage;
use App\Models\MetaPost;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaReportTest extends TestCase
{
    use RefreshDatabase;

    private User $smm;

    private User $customer;

    private Client $client;

    private MetaPage $page;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-20 10:00:00');
        $this->page = MetaPage::create(['page_id' => '111', 'name' => 'Acme Yoga', 'access_token' => 'page-token', 'ig_user_id' => '999']);
        $account = MetaAdAccount::create(['account_id' => 'act_55', 'name' => 'Acme Ads', 'currency' => 'INR']);
        $this->client = Client::create(['name' => 'Acme', 'meta_page_id' => $this->page->id, 'meta_ad_account_id' => $account->id]);
        MetaConnection::create(['user_token' => 'user-token']);
        $this->smm = User::factory()->create(['role' => Role::SocialMediaManager]);
        $this->customer = User::factory()->create(['role' => Role::Client, 'client_id' => $this->client->id]);
    }

    public function test_posting_report_shows_facebook_and_instagram_numbers(): void
    {
        $calendar = Calendar::create(['client_id' => $this->client->id, 'month' => '2026-11-01', 'created_by' => $this->smm->id, 'status' => CalendarStatus::ClientApproved]);
        $item = $calendar->items()->create(['post_date' => '2026-11-05', 'post_types' => ['facebook_post', 'instagram_post'], 'brief' => 'Diwali offer']);
        $task = Task::forceCreate(['calendar_item_id' => $item->id, 'assigned_to' => $this->smm->id, 'assigned_by' => $this->smm->id, 'deadline' => '2026-11-03']);
        $content = Content::forceCreate(['task_id' => $task->id, 'post_type' => 'post', 'approver_id' => $this->smm->id, 'status' => ContentStatus::Published, 'current_version' => 1]);
        MetaPost::create(['content_id' => $content->id, 'meta_page_id' => $this->page->id, 'platform' => 'facebook', 'status' => 'done', 'post_id' => '111_222']);
        MetaPost::create(['content_id' => $content->id, 'meta_page_id' => $this->page->id, 'platform' => 'instagram', 'status' => 'done', 'post_id' => '777']);
        Http::fake([
            '*/111_222/insights*' => Http::response(['data' => [['values' => [['value' => 1500]]]]]),
            '*/111_222?*' => Http::response(['reactions' => ['summary' => ['total_count' => 40]], 'comments' => ['summary' => ['total_count' => 6]], 'shares' => ['count' => 3]]),
            '*/777/insights*' => Http::response(['error' => ['message' => 'Missing permission']], 400),
            '*/777?*' => Http::response(['like_count' => 90, 'comments_count' => 12]),
        ]);

        $this->actingAs($this->customer)->get(route('reports.posting', ['month' => '2026-11']))->assertOk()
            ->assertSeeInOrder(['Diwali offer', 'Facebook', '1,500', '40', '6', '3', 'Instagram', '—', '90', '12'])
            ->assertSee('130'); // likes total
    }

    public function test_ad_report_shows_spend_and_results(): void
    {
        MetaAd::create(['client_id' => $this->client->id, 'meta_ad_account_id' => $this->client->meta_ad_account_id, 'name' => 'Diwali WhatsApp', 'goal' => 'whatsapp',
            'daily_budget' => 300, 'start_on' => '2026-11-01', 'end_on' => '2026-11-08', 'age_min' => 22, 'age_max' => 55, 'gender' => 'all',
            'primary_text' => 'x', 'image_path' => 'ads/a.jpg', 'status' => 'active', 'ad_id' => 'ad1']);
        Http::fake(['*/ad1/insights*' => Http::response(['data' => [['spend' => '2400', 'reach' => '18000', 'impressions' => '30000', 'clicks' => '500',
            'actions' => [['action_type' => 'onsite_conversion.messaging_conversation_started_7d', 'value' => '60']]]]])]);

        $this->actingAs($this->smm)->get(route('reports.ads', ['client' => $this->client->id, 'month' => '2026-11']))->assertOk()
            ->assertSee('Diwali WhatsApp')->assertSee('₹2,400')->assertSee('18,000')->assertSee('₹40.00');
    }

    public function test_leads_report_counts_and_customer_sees_only_own_brand(): void
    {
        MetaLead::create(['meta_page_id' => $this->page->id, 'client_id' => $this->client->id, 'lead_id' => 'l1', 'form_name' => 'Free trial', 'name' => 'Neha', 'fields' => [], 'lead_at' => '2026-11-05 10:00', 'status' => 'won']);
        MetaLead::create(['meta_page_id' => $this->page->id, 'client_id' => $this->client->id, 'lead_id' => 'l2', 'form_name' => 'Free trial', 'name' => 'Amit', 'fields' => [], 'lead_at' => '2026-10-05 10:00']);
        $other = Client::create(['name' => 'Zen']);

        $this->actingAs($this->customer)->get(route('reports.leads', ['client' => $other->id, 'month' => '2026-11']))->assertOk()
            ->assertSee('Acme')->assertSee('Neha')->assertDontSee('Amit')->assertDontSee('Zen');
        $this->actingAs(User::factory()->create(['role' => Role::GraphicDesigner]))->get(route('reports.leads'))->assertForbidden();
    }

    public function test_sidebar_is_grouped_by_work(): void
    {
        $this->actingAs($this->smm)->get(route('dashboard'))->assertOk()
            ->assertSeeInOrder(['Content', 'Calendars', 'Social media', 'Comments', 'Meta leads', 'Meta ads', 'Ads', 'Team', 'Leaderboard'])
            ->assertDontSee('Accounts');
    }
}
