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

    public function test_ai_writes_a_comment_reply_in_the_comments_language(): void
    {
        $post = $this->publishedPost('instagram', '777');
        $this->client->update(['phone' => '9800000000']);
        $comment = MetaComment::create(['meta_post_id' => $post->id, 'client_id' => $this->client->id, 'platform' => 'instagram', 'comment_id' => 'i1',
            'author' => 'Riya', 'message' => 'Fees kitni hai?', 'commented_at' => now()]);
        config(['services.groq.key' => 'test-key']);
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => '{"reply": "Hi Riya! Details ke liye DM karein."}']]]])]);

        $this->actingAs($this->smm)->post(route('meta.comments.ai', $comment))
            ->assertSessionHas('_old_input', ['reply' => 'Hi Riya! Details ke liye DM karein.', 'ai_comment' => $comment->id]);
        Http::assertSent(fn (Request $r) => str_contains($r['messages'][0]['content'], 'same language and script as the comment')
            && str_contains($r['messages'][0]['content'], 'call the brand phone') && str_contains($r['messages'][1]['content'], 'Comment: Fees kitni hai?'));
        $this->assertNull($comment->fresh()->replied_at); // nothing posted until Reply

        $this->actingAs($this->smm)->withSession(['_old_input' => ['reply' => 'Hi Riya!', 'ai_comment' => $comment->id]])
            ->get(route('meta.comments'))->assertSee('Write with AI')->assertSee('Hi Riya!');
        $this->actingAs(User::factory()->create(['role' => Role::GraphicDesigner]))->post(route('meta.comments.ai', $comment))->assertForbidden();
    }

    public function test_comments_filter_and_ignore(): void
    {
        $fb = $this->publishedPost('facebook', '111_222');
        $ig = $this->publishedPost('instagram', '777');
        $make = fn ($post, $platform, $id, $text) => MetaComment::create(['meta_post_id' => $post->id, 'client_id' => $this->client->id, 'platform' => $platform,
            'comment_id' => $id, 'author' => 'Riya', 'message' => $text, 'commented_at' => now()]);
        $spam = $make($fb, 'facebook', 'c1', 'Buy followers cheap');
        $make($ig, 'instagram', 'c2', 'Timings please');

        $this->actingAs($this->smm)->get(route('meta.comments', ['platform' => 'instagram']))->assertSee('Timings please')->assertDontSee('Buy followers cheap');
        $this->actingAs($this->smm)->post(route('meta.comments.ignore', $spam))->assertSessionHas('status', 'Comment ignored.');
        $this->assertSame($this->smm->id, $spam->fresh()->ignored_by);
        $this->actingAs($this->smm)->get(route('meta.comments'))->assertDontSee('Buy followers cheap')->assertSee('Timings please');
        $this->actingAs($this->smm)->get(route('meta.comments', ['show' => 'ignored']))->assertSee('Buy followers cheap')->assertSee('Undo')->assertDontSee('Timings please');
        $this->actingAs($this->smm)->get(route('dashboard'))->assertSeeInOrder(['Comments', '1']); // sidebar count leaves the ignored one out

        $this->actingAs($this->smm)->post(route('meta.comments.ignore', $spam))->assertSessionHas('status', 'Comment is back in To reply.');
        $this->assertNull($spam->fresh()->ignored_at);
        $this->actingAs(User::factory()->create(['role' => Role::GraphicDesigner]))->post(route('meta.comments.ignore', $spam))->assertForbidden();
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

    private function lead(string $id, string $name, ?string $phone, array $extra = []): MetaLead
    {
        return MetaLead::create($extra + ['meta_page_id' => $this->page->id, 'client_id' => $this->client->id, 'lead_id' => $id,
            'name' => $name, 'phone' => $phone, 'phone_key' => MetaLead::phoneKey($phone), 'fields' => ['city' => 'Pune'], 'lead_at' => now()]);
    }

    public function test_same_phone_again_is_tagged_repeat(): void
    {
        $this->lead('old', 'Neha', '09876543210');
        Http::fake([
            '*/111/leadgen_forms*' => Http::response(['data' => [['id' => 'f1', 'name' => 'Free trial']]]),
            '*/f1/leads*' => Http::response(['data' => [
                ['id' => 'l1', 'created_time' => now()->toIso8601String(), 'field_data' => [['name' => 'full_name', 'values' => ['Neha P']], ['name' => 'phone_number', 'values' => ['+91 98765 43210']]]],
                ['id' => 'l2', 'created_time' => now()->toIso8601String(), 'field_data' => [['name' => 'full_name', 'values' => ['Amit']], ['name' => 'phone_number', 'values' => ['+919999988888']]]],
            ]]),
        ]);

        $this->assertSame(2, MetaInbox::syncLeads());
        $this->assertTrue(MetaLead::where('lead_id', 'l1')->sole()->is_repeat);
        $this->assertFalse(MetaLead::where('lead_id', 'l2')->sole()->is_repeat);
        $this->actingAs($this->smm)->get(route('meta.leads'))->assertSee('Repeat');
    }

    public function test_remarks_keep_history_and_set_the_follow_up(): void
    {
        $lead = $this->lead('l1', 'Neha', '9876543210');
        $customer = User::factory()->create(['role' => Role::Client, 'client_id' => $this->client->id]);

        $this->actingAs($this->smm)->post(route('meta.leads.remark', $lead), ['remark' => 'No answer', 'follow_up_on' => today()->addDay()->toDateString()])->assertRedirect();
        $this->assertSame(['contacted', today()->addDay()->toDateString()], [$lead->fresh()->status, $lead->fresh()->follow_up_on->toDateString()]);
        $this->travel(1)->minute();
        $this->actingAs($customer)->post(route('meta.leads.remark', $lead), ['remark' => 'Price sent']);
        $this->assertNull($lead->fresh()->follow_up_on);
        $this->assertSame(['Price sent', 'No answer'], $lead->remarks()->pluck('remark')->all());

        $this->actingAs($customer)->get(route('meta.leads'))->assertSee('Price sent')->assertSee('history (2)');
        $this->actingAs($this->smm)->post(route('meta.leads.remark', $lead), ['remark' => 'x', 'follow_up_on' => today()->subDay()->toDateString()])->assertSessionHasErrors('follow_up_on');
        $other = User::factory()->create(['role' => Role::Client, 'client_id' => Client::create(['name' => 'Zen'])->id]);
        $this->actingAs($other)->post(route('meta.leads.remark', $lead), ['remark' => 'Hi'])->assertForbidden();
    }

    public function test_search_follow_up_filter_and_daily_reminder(): void
    {
        $this->lead('l1', 'Neha Patil', '+91 98765 43210', ['follow_up_on' => today()]);
        $this->lead('l2', 'Amit Shah', '9999988888', ['follow_up_on' => today()->subDays(2)]);
        $this->lead('l3', 'Ravi', '9111122222', ['follow_up_on' => today()->addDay()]);
        $this->lead('l4', 'Sunil', '9333344444', ['follow_up_on' => today(), 'status' => 'won']);
        $customer = User::factory()->create(['role' => Role::Client, 'client_id' => $this->client->id]);

        $this->assertSame(2, MetaInbox::remindFollowUps());
        $this->assertStringContainsString('2 lead follow-up(s) for Acme (1 missed)', $customer->notifications()->sole()->data['message']);
        $this->assertSame(1, $this->smm->notifications()->count());

        $this->actingAs($this->smm)->get(route('meta.leads', ['q' => '98765']))->assertSee('Neha Patil')->assertDontSee('Amit Shah');
        $this->actingAs($this->smm)->get(route('meta.leads', ['q' => 'amit']))->assertSee('Amit Shah')->assertDontSee('Neha Patil');
        $this->actingAs($this->smm)->get(route('meta.leads', ['followup' => 'today']))
            ->assertSee('Neha Patil')->assertSee('Amit Shah')->assertDontSee('Ravi')->assertDontSee('Sunil')->assertSee('(missed)');
    }

    public function test_team_gets_lead_emails_only_for_mega_techzy(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $customer = User::factory()->create(['role' => Role::Client, 'client_id' => $this->client->id]);
        $this->lead('l1', 'Neha Patil', '9876543210', ['follow_up_on' => today()]);
        MetaInbox::remindFollowUps();

        $via = fn (User $u) => \Illuminate\Support\Facades\Notification::sent($u, \App\Notifications\WorkflowNotification::class)->sole()->via($u);
        $this->assertNotContains('mail', $via($this->smm)); // a client brand: bell + push for the team
        $this->assertContains('database', $via($this->smm));
        $this->assertContains('mail', $via($customer)); // the brand's own Customer still gets the email

        \Illuminate\Support\Facades\Notification::fake();
        $this->client->update(['is_internal' => true]); // Mega Techzy's own leads
        MetaInbox::remindFollowUps();
        $this->assertContains('mail', $via($this->smm));
    }

    public function test_leads_export_as_csv_with_answers_and_remarks(): void
    {
        $lead = $this->lead('l1', 'Neha Patil', '9876543210');
        $lead->remarks()->create(['remark' => 'Call back', 'user_id' => $this->smm->id]);
        $this->lead('l2', 'Amit Shah', '9999988888', ['status' => 'lost']);

        $csv = $this->actingAs($this->smm)->get(route('meta.leads.export', ['status' => 'new']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Neha Patil', $csv);
        $this->assertStringContainsString('Call back', $csv);
        $this->assertStringContainsString(',city', $csv);
        $this->assertStringNotContainsString('Amit Shah', $csv);

        $other = User::factory()->create(['role' => Role::Client, 'client_id' => Client::create(['name' => 'Zen'])->id]);
        $this->assertStringNotContainsString('Neha Patil', $this->actingAs($other)->get(route('meta.leads.export'))->streamedContent());
    }
}
