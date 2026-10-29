<?php

namespace Tests\Feature;

use App\Enums\CalendarStatus;
use App\Enums\Role;
use App\Models\Calendar;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private User $team;

    private User $approver;

    private User $clientUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = Client::create(['name' => 'Acme']);
        // The SMM creates calendars; the Designer can only view
        $this->team = User::factory()->create(['role' => Role::SocialMediaManager]);
        $this->approver = User::factory()->create(['role' => Role::GraphicDesigner]);
        $this->clientUser = User::factory()->create(['role' => Role::Client, 'client_id' => $this->client->id]);
    }

    private function draftWithItem(): Calendar
    {
        $this->actingAs($this->team)->post('/calendars', ['client_id' => $this->client->id, 'month' => '2026-11']);
        $calendar = Calendar::firstOrFail();
        $this->actingAs($this->team)->post("/calendars/{$calendar->id}/items", [
            'post_date' => '2026-11-05', 'format' => 'carousel', 'platforms' => ['linkedin', 'instagram'], 'creative_copy' => 'Diwali offer',
        ])->assertSessionHasNoErrors();

        return $calendar;
    }

    private function submitAndOpsApprove(Calendar $calendar): void
    {
        $this->actingAs($this->team)->post("/calendars/{$calendar->id}/submit");
        $ops = User::factory()->create(['role' => Role::Manager]);
        $this->actingAs($ops)->post("/calendars/{$calendar->id}/review", ['action' => 'approved']);
    }

    public function test_full_flow_smm_to_ops_to_customer_with_auto_assign(): void
    {
        // Designer cannot create calendars
        $this->actingAs($this->approver)->get('/calendars/create')->assertForbidden();

        $calendar = $this->draftWithItem();

        // Customer cannot see drafts
        $this->actingAs($this->clientUser)->get("/calendars/{$calendar->id}")->assertRedirect(route('calendars.index'))->assertSessionHas('popup');

        // SMM submit => with the Ops Manager; Customer cannot see it yet, SMM cannot self-approve
        $this->actingAs($this->team)->post("/calendars/{$calendar->id}/submit");
        $this->assertSame(CalendarStatus::Submitted, $calendar->fresh()->status);
        $this->actingAs($this->clientUser)->get("/calendars/{$calendar->id}")->assertRedirect(route('calendars.index'))->assertSessionHas('popup');
        $this->actingAs($this->team)->post("/calendars/{$calendar->id}/review", ['action' => 'approved'])->assertForbidden();

        $ops = User::factory()->create(['role' => Role::Manager]);
        $this->actingAs($ops)->post("/calendars/{$calendar->id}/review", ['action' => 'approved']);
        $this->assertSame(CalendarStatus::ApproverApproved, $calendar->fresh()->status);
        $this->assertSame(1, $this->clientUser->notifications()->count());

        $this->actingAs($this->clientUser)->get("/calendars/{$calendar->id}")->assertOk()->assertSee('Diwali offer')->assertSee('LinkedIn Carousel, Instagram Carousel');
        // Customer cannot approve the whole calendar at once, only row by row
        $this->actingAs($this->clientUser)->post("/calendars/{$calendar->id}/review", ['action' => 'approved'])->assertForbidden();
        $item = $calendar->items()->first();
        $this->actingAs($this->clientUser)->post("/calendars/{$calendar->id}/items/{$item->id}/review", ['action' => 'approved']);
        $this->assertSame(CalendarStatus::ClientApproved, $calendar->fresh()->status);

        // On Customer approval the task is auto-assigned (to the Designer)
        $task = $calendar->items()->first()->task;
        $this->assertNotNull($task);
        $this->assertSame($this->approver->id, $task->assigned_to);

        // no editing after approval
        $this->actingAs($this->team)->get("/calendars/{$calendar->id}/items/create")->assertForbidden();
    }

    public function test_auto_assign_picks_least_busy_member_of_right_role(): void
    {
        $busy = $this->approver; // Graphic Designer
        $free = User::factory()->create(['role' => Role::GraphicDesigner]);
        $editor = User::factory()->create(['role' => Role::VideoEditor]);
        // $busy already has one pending task
        $old = Calendar::create(['client_id' => $this->client->id, 'month' => '2026-10-01', 'created_by' => $this->team->id, 'status' => CalendarStatus::ClientApproved]);
        $old->items()->create(['post_date' => '2026-10-20', 'post_types' => ['instagram_post']])
            ->task()->create(['assigned_to' => $busy->id, 'assigned_by' => $this->team->id, 'deadline' => '2026-10-18']);

        $calendar = Calendar::create(['client_id' => $this->client->id, 'month' => '2026-11-01', 'created_by' => $this->team->id, 'status' => CalendarStatus::ClientApproved]);
        $post = $calendar->items()->create(['post_date' => '2026-11-05', 'post_types' => ['instagram_post']]);
        $reel = $calendar->items()->create(['post_date' => '2026-11-06', 'post_types' => ['instagram_reel']]);

        \App\Models\Task::autoAssign($calendar, $this->team->id);

        $this->assertSame($free->id, $post->fresh()->task->assigned_to);
        $this->assertSame($editor->id, $reel->fresh()->task->assigned_to);
    }

    public function test_auto_assign_follows_brand_team(): void
    {
        $other = Client::create(['name' => 'Other brand']);
        $mine = User::factory()->create(['role' => Role::GraphicDesigner]);      // ticked for this brand
        $elsewhere = User::factory()->create(['role' => Role::GraphicDesigner]); // ticked only for the other brand
        $mine->brands()->sync([$this->client->id]);
        $elsewhere->brands()->sync([$other->id]);
        $this->approver->brands()->sync([$other->id]); // the existing designer works elsewhere too

        $calendar = Calendar::create(['client_id' => $this->client->id, 'month' => '2026-11-01', 'created_by' => $this->team->id, 'status' => CalendarStatus::ClientApproved]);
        $a = $calendar->items()->create(['post_date' => '2026-11-05', 'post_types' => ['instagram_post']]);
        $b = $calendar->items()->create(['post_date' => '2026-11-06', 'post_types' => ['instagram_post']]);
        \App\Models\Task::autoAssign($calendar, $this->team->id);

        // both go to the brand's designer, even though she gets busier than the others
        $this->assertSame($mine->id, $a->fresh()->task->assigned_to);
        $this->assertSame($mine->id, $b->fresh()->task->assigned_to);

        // a brand nobody is ticked for: members with no brands, else anyone of the role
        $third = Client::create(['name' => 'Third']);
        $cal3 = Calendar::create(['client_id' => $third->id, 'month' => '2026-11-01', 'created_by' => $this->team->id, 'status' => CalendarStatus::ClientApproved]);
        $c = $cal3->items()->create(['post_date' => '2026-11-07', 'post_types' => ['instagram_post']]);
        \App\Models\Task::autoAssign($cal3, $this->team->id);
        $this->assertNotNull($c->fresh()->task);
    }

    public function test_drafts_only_for_creator_admin_and_ops(): void
    {
        $draft = Calendar::create(['client_id' => $this->client->id, 'month' => '2026-12-01', 'created_by' => $this->team->id, 'status' => CalendarStatus::Draft]);
        $otherSmm = User::factory()->create(['role' => Role::SocialMediaManager]);
        $ops = User::factory()->create(['role' => Role::Manager]);
        $admin = User::factory()->create(['role' => Role::Admin]);

        $this->actingAs($this->team)->get("/calendars/{$draft->id}")->assertOk();
        $this->actingAs($ops)->get("/calendars/{$draft->id}")->assertOk();
        $this->actingAs($admin)->get('/calendars')->assertSee('December 2026');
        $this->actingAs($otherSmm)->get("/calendars/{$draft->id}")->assertRedirect(route('calendars.index'))->assertSessionHas('popup');
        $this->actingAs($otherSmm)->get('/calendars')->assertDontSee('December 2026');
        $this->actingAs($this->approver)->get("/calendars/{$draft->id}")->assertRedirect(route('calendars.index'))->assertSessionHas('popup');
    }

    public function test_editor_sees_only_own_rows_in_calendar_and_schedule(): void
    {
        $editor = User::factory()->create(['role' => Role::VideoEditor]);
        $calendar = Calendar::create(['client_id' => $this->client->id, 'month' => '2026-11-01', 'created_by' => $this->team->id, 'status' => CalendarStatus::ClientApproved]);
        $mine = $calendar->items()->create(['post_date' => '2026-11-05', 'post_types' => ['instagram_reel'], 'creative_copy' => 'MY REEL ROW']);
        $theirs = $calendar->items()->create(['post_date' => '2026-11-06', 'post_types' => ['instagram_post'], 'creative_copy' => 'DESIGNER POST ROW']);
        $mine->task()->create(['assigned_to' => $editor->id, 'assigned_by' => $this->team->id, 'deadline' => '2026-11-03']);
        $theirs->task()->create(['assigned_to' => $this->approver->id, 'assigned_by' => $this->team->id, 'deadline' => '2026-11-04']);
        $empty = Calendar::create(['client_id' => $this->client->id, 'month' => '2026-10-01', 'created_by' => $this->team->id, 'status' => CalendarStatus::ClientApproved]);

        $this->actingAs($editor)->get("/calendars/{$calendar->id}")->assertOk()->assertSee('MY REEL ROW')->assertDontSee('DESIGNER POST ROW');
        $this->actingAs($editor)->get("/calendars/{$empty->id}")->assertRedirect(route('calendars.index'))->assertSessionHas('popup');
        $this->actingAs($editor)->get('/schedule?month=2026-11')->assertOk()
            ->assertSee(route('tasks.show', $mine->task), false)->assertDontSee(route('tasks.show', $theirs->task), false);
        $this->actingAs($this->team)->get("/calendars/{$calendar->id}")->assertSee('DESIGNER POST ROW'); // SMM sees all
    }

    public function test_admin_sets_team_brands(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $other = Client::create(['name' => 'Other brand']);
        $this->actingAs($admin)->put("/admin/users/{$this->approver->id}", [
            'name' => $this->approver->name, 'email' => $this->approver->email, 'role' => $this->approver->role->value,
            'is_active' => 1, 'brands' => [$this->client->id, $other->id],
        ])->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing([$this->client->id, $other->id], $this->approver->brands()->pluck('clients.id')->all());

        // a Customer never keeps team brands
        $this->actingAs($admin)->put("/admin/users/{$this->clientUser->id}", [
            'name' => $this->clientUser->name, 'email' => $this->clientUser->email, 'role' => 'client',
            'client_id' => $this->client->id, 'is_active' => 1, 'brands' => [$other->id],
        ])->assertSessionHasNoErrors();
        $this->assertSame(0, $this->clientUser->brands()->count());
    }

    public function test_customer_checks_line_by_line(): void
    {
        $calendar = $this->draftWithItem();
        $this->actingAs($this->team)->post("/calendars/{$calendar->id}/items", ['post_date' => '2026-11-09', 'post_types' => ['instagram_post']]);
        [$row1, $row2] = $calendar->items()->get()->all();
        $this->submitAndOpsApprove($calendar);
        $url = fn ($row) => "/calendars/{$calendar->id}/items/{$row->id}/review";

        // revision needs a comment
        $this->actingAs($this->clientUser)->post($url($row1), ['action' => 'revision'])->assertSessionHasErrors('comment');

        // Row 1 revision, Row 2 still open => calendar stays with the Customer
        $this->actingAs($this->clientUser)->post($url($row1), ['action' => 'revision', 'comment' => 'Change the date']);
        $this->assertSame(CalendarStatus::ApproverApproved, $calendar->fresh()->status);

        // Row 2 approved => all rows checked => there was a revision, back to the SMM
        $this->actingAs($this->clientUser)->post($url($row2), ['action' => 'approved']);
        $this->assertSame(CalendarStatus::Draft, $calendar->fresh()->status);
        $this->actingAs($this->team)->get("/calendars/{$calendar->id}")->assertSee('Change the date');

        // SMM edits row 1 => only it needs a re-check; row 2 stays approved
        $this->actingAs($this->team)->put("/calendars/{$calendar->id}/items/{$row1->id}", ['post_date' => '2026-11-12', 'post_types' => $row1->post_types]);
        $this->assertNull($row1->fresh()->client_status);
        $this->assertSame('approved', $row2->fresh()->client_status);

        $this->submitAndOpsApprove($calendar);
        $this->actingAs($this->clientUser)->post($url($row1), ['action' => 'approved']);
        $this->assertSame(CalendarStatus::ClientApproved, $calendar->fresh()->status);
    }

    public function test_other_customer_cannot_review_rows(): void
    {
        $calendar = $this->draftWithItem();
        $this->submitAndOpsApprove($calendar);
        $other = User::factory()->create(['role' => Role::Client, 'client_id' => Client::create(['name' => 'Other'])->id]);
        $item = $calendar->items()->first();

        $this->actingAs($other)->post("/calendars/{$calendar->id}/items/{$item->id}/review", ['action' => 'approved'])->assertForbidden();
        $this->assertNull($item->fresh()->client_status);
    }

    public function test_other_client_cannot_see_calendar(): void
    {
        $calendar = $this->draftWithItem();
        $calendar->update(['status' => CalendarStatus::ApproverApproved]);
        $other = User::factory()->create(['role' => Role::Client, 'client_id' => Client::create(['name' => 'Other'])->id]);

        $this->actingAs($other)->get("/calendars/{$calendar->id}")->assertRedirect(route('calendars.index'))->assertSessionHas('popup');
        $this->actingAs($other)->get('/calendars')->assertDontSee('Acme');
    }

    public function test_item_date_must_be_inside_calendar_month_and_empty_calendar_cannot_submit(): void
    {
        $this->actingAs($this->team)->post('/calendars', ['client_id' => $this->client->id, 'month' => '2026-11']);
        $calendar = Calendar::firstOrFail();

        $this->actingAs($this->team)->post("/calendars/{$calendar->id}/submit")->assertForbidden();
        $this->actingAs($this->team)->post("/calendars/{$calendar->id}/items", ['post_date' => '2026-12-01', 'post_types' => ['instagram_post']])
            ->assertSessionHasErrors('post_date');
    }

    public function test_draft_rows_may_have_no_date_but_submit_needs_dates(): void
    {
        $calendar = $this->draftWithItem();
        $this->actingAs($this->team)->post("/calendars/{$calendar->id}/import", [
            'rows' => "Date\tPost type\tCopy\n\tInstagram, Facebook Reel\tDesk reset\n",
        ])->assertSessionHasNoErrors();
        $undated = $calendar->items()->whereNull('post_date')->sole();
        $this->assertSame(['instagram_reel', 'facebook_reel'], $undated->post_types);
        $this->assertSame($undated->id, $calendar->fresh()->items->last()->id); // undated rows last

        $this->actingAs($this->team)->get("/calendars/{$calendar->id}")->assertOk()->assertSee('No date');
        $this->actingAs($this->team)->post("/calendars/{$calendar->id}/submit")->assertSessionHas('status', 'Add a date to 1 row before submitting.');
        $this->assertSame(CalendarStatus::Draft, $calendar->fresh()->status);

        $this->actingAs($this->team)->put("/calendars/{$calendar->id}/items/{$undated->id}", [
            'post_date' => '2026-11-20', 'format' => 'reel', 'platforms' => ['instagram', 'facebook'],
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->team)->post("/calendars/{$calendar->id}/submit");
        $this->assertSame(CalendarStatus::Submitted, $calendar->fresh()->status);
    }

    public function test_post_types_must_be_valid_options(): void
    {
        $this->actingAs($this->team)->post('/calendars', ['client_id' => $this->client->id, 'month' => '2026-11']);
        $calendar = Calendar::firstOrFail();

        $this->actingAs($this->team)->post("/calendars/{$calendar->id}/items", ['post_date' => '2026-11-03', 'post_types' => []])
            ->assertSessionHasErrors('post_types');
        $this->actingAs($this->team)->post("/calendars/{$calendar->id}/items", ['post_date' => '2026-11-03', 'post_types' => ['youtube_carousel']])
            ->assertSessionHasErrors('post_types.0');
    }

    public function test_one_format_per_row(): void
    {
        $this->actingAs($this->team)->post('/calendars', ['client_id' => $this->client->id, 'month' => '2026-11']);
        $calendar = Calendar::firstOrFail();
        $url = "/calendars/{$calendar->id}/items";

        // Post + Reel in one row => refused (make two rows instead)
        $this->actingAs($this->team)->post($url, ['post_date' => '2026-11-05', 'post_types' => ['instagram_post', 'instagram_reel']])
            ->assertSessionHasErrors('post_types');
        // YouTube has no carousel
        $this->actingAs($this->team)->post($url, ['post_date' => '2026-11-05', 'format' => 'carousel', 'platforms' => ['youtube']])
            ->assertSessionHasErrors('post_types.0');
        // Same day, two rows: fine
        $this->actingAs($this->team)->post($url, ['post_date' => '2026-11-05', 'format' => 'reel', 'platforms' => ['instagram']])->assertSessionHasNoErrors();
        $this->actingAs($this->team)->post($url, ['post_date' => '2026-11-05', 'format' => 'post', 'platforms' => ['instagram', 'facebook', 'linkedin']])->assertSessionHasNoErrors();

        $this->assertSame([['instagram_reel'], ['instagram_post', 'facebook_post', 'linkedin_post']],
            $calendar->items()->orderBy('id')->pluck('post_types')->all());
    }
}
