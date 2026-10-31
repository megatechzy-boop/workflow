<?php

namespace Tests\Feature;

use App\Enums\CalendarStatus;
use App\Enums\ContentStatus;
use App\Enums\Role;
use App\Models\Calendar;
use App\Models\Client;
use App\Models\Content;
use App\Models\MetaComment;
use App\Models\MetaLead;
use App\Models\MetaPage;
use App\Models\MetaPost;
use App\Models\Task;
use App\Models\User;
use App\Support\MetaInbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaInboxTest extends TestCase
{
    use RefreshDatabase;

    private User $smm;

    private Client $client;

    private MetaPage $page;

    protected function setUp(): void
    {
        parent::setUp();
        $this->page = MetaPage::create(['page_id' => '111', 'name' => 'Acme Yoga', 'access_token' => 'page-token', 'ig_user_id' => '999', 'ig_username' => 'acmeyoga']);
        $this->client = Client::create(['name' => 'Acme', 'meta_page_id' => $this->page->id]);
        $this->smm = User::factory()->create(['role' => Role::SocialMediaManager]);
    }

    private function publishedPost(string $platform, string $postId): MetaPost
    {
        $calendar = Calendar::where('client_id', $this->client->id)->first()
            ?? Calendar::create(['client_id' => $this->client->id, 'month' => '2026-11-01', 'created_by' => $this->smm->id, 'status' => CalendarStatus::ClientApproved]);
        $item = $calendar->items()->create(['post_date' => '2026-11-05', 'post_types' => ['facebook_post']]);
        $task = Task::forceCreate(['calendar_item_id' => $item->id, 'assigned_to' => $this->smm->id, 'assigned_by' => $this->smm->id, 'deadline' => '2026-11-03']);
        $content = Content::forceCreate(['task_id' => $task->id, 'post_type' => 'post', 'approver_id' => $this->smm->id, 'status' => ContentStatus::Published, 'current_version' => 1]);

        return MetaPost::create(['content_id' => $content->id, 'meta_page_id' => $this->page->id, 'platform' => $platform, 'status' => 'done', 'post_id' => $postId]);
    }

    public function test_comments_are_synced_once_and_replied_from_the_app(): void
    {
        $this->publishedPost('facebook', '111_222');
        $this->publishedPost('instagram', '777');
        Http::fake([
            '*/111_222/comments*' => Http::response(['data' => [
                ['id' => 'c1', 'from' => ['id' => '500', 'name' => 'Riya'], 'message' => 'Price?', 'created_time' => '2026-11-05T10:00:00+0000'],
                ['id' => 'c2', 'from' => ['id' => '111', 'name' => 'Acme Yoga'], 'message' => 'Thanks!', 'created_time' => '2026-11-05T10:05:00+0000'],
            ]]),
            '*/777/comments*' => Http::response(['data' => [['id' => 'i1', 'username' => 'amit', 'text' => 'Timings?', 'timestamp' => '2026-11-05T11:00:00+0000']]]),
            '*/i1/replies' => Http::response(['id' => 'r1']),
        ]);

        $this->assertSame(2, MetaInbox::syncComments()); // our own page reply skipped
        $this->assertSame(0, MetaInbox::syncComments()); // no duplicates
        $this->assertSame(2, $this->smm->notifications()->count()); // one per post

        $this->actingAs($this->smm)->get(route('meta.comments'))->assertOk()->assertSee('Price?')->assertSee('Timings?')->assertDontSee('Thanks!');

        $ig = MetaComment::where('comment_id', 'i1')->sole();
        $this->actingAs($this->smm)->post(route('meta.comments.reply', $ig), ['reply' => '7 am and 6 pm'])->assertSessionHas('status', 'Reply posted.');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/i1/replies') && $r['message'] === '7 am and 6 pm');
        $this->assertNotNull($ig->fresh()->replied_at);
        $this->actingAs($this->smm)->get(route('meta.comments'))->assertDontSee('Timings?');
    }

    public function test_comments_respect_brands(): void
    {
        $this->publishedPost('facebook', '111_222');
        Http::fake(['*/comments*' => Http::response(['data' => [['id' => 'c1', 'from' => ['id' => '500', 'name' => 'Riya'], 'message' => 'Price?', 'created_time' => '2026-11-05T10:00:00+0000']]])]);
        MetaInbox::syncComments();

        $this->smm->brands()->sync([Client::create(['name' => 'Zen'])->id]);
        $this->actingAs($this->smm)->get(route('meta.comments'))->assertDontSee('Price?');
        $this->actingAs($this->smm)->post(route('meta.comments.reply', MetaComment::sole()), ['reply' => 'Hi'])->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => Role::GraphicDesigner]))->get(route('meta.comments'))->assertForbidden();
    }

    public function test_leads_are_synced_and_customer_sees_only_their_own(): void
    {
        Http::fake([
            '*/111/leadgen_forms*' => Http::response(['data' => [['id' => 'f1', 'name' => 'Free trial']]]),
            '*/f1/leads*' => Http::response(['data' => [['id' => 'l1', 'created_time' => '2026-11-05T10:00:00+0000', 'field_data' => [
                ['name' => 'full_name', 'values' => ['Neha Patil']], ['name' => 'phone_number', 'values' => ['+919876543210']],
                ['name' => 'preferred_time', 'values' => ['Morning']],
            ]]]]),
        ]);
        $customer = User::factory()->create(['role' => Role::Client, 'client_id' => $this->client->id]);

        $this->assertSame(1, MetaInbox::syncLeads());
        $this->assertSame(0, MetaInbox::syncLeads());
        $lead = MetaLead::sole();
        $this->assertSame(['Neha Patil', '+919876543210', $this->client->id, '919876543210'], [$lead->name, $lead->phone, $lead->client_id, $lead->phoneDigits()]);
        $this->assertSame(1, $customer->notifications()->count());

        $this->actingAs($customer)->get(route('meta.leads'))->assertOk()->assertSee('Neha Patil')->assertSee('Morning');
        $this->actingAs($customer)->put(route('meta.leads.update', $lead), ['status' => 'contacted']);
        $this->assertSame('contacted', $lead->fresh()->status);

        $other = User::factory()->create(['role' => Role::Client, 'client_id' => Client::create(['name' => 'Zen'])->id]);
        $this->actingAs($other)->get(route('meta.leads'))->assertDontSee('Neha Patil');
        $this->actingAs($other)->put(route('meta.leads.update', $lead), ['status' => 'won'])->assertForbidden();
        $this->actingAs($this->smm)->get(route('meta.leads'))->assertSee('Neha Patil')->assertSee('Acme');
    }
}
