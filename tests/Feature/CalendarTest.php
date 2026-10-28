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
        // Calendar ab SMM banata hai; Designer sirf dekh sakta hai
        $this->team = User::factory()->create(['role' => Role::SocialMediaManager]);
        $this->approver = User::factory()->create(['role' => Role::GraphicDesigner]);
        $this->clientUser = User::factory()->create(['role' => Role::Client, 'client_id' => $this->client->id]);
    }

    private function draftWithItem(): Calendar
    {
        $this->actingAs($this->team)->post('/calendars', ['client_id' => $this->client->id, 'month' => '2026-11']);
        $calendar = Calendar::firstOrFail();
        $this->actingAs($this->team)->post("/calendars/{$calendar->id}/items", [
            'post_date' => '2026-11-05', 'post_types' => ['linkedin_carousel', 'instagram_reel'], 'creative_copy' => 'Diwali offer',
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
        // Designer calendar nahi bana sakta
        $this->actingAs($this->approver)->get('/calendars/create')->assertForbidden();

        $calendar = $this->draftWithItem();

        // Customer draft nahi dekh sakta
        $this->actingAs($this->clientUser)->get("/calendars/{$calendar->id}")->assertForbidden();

        // SMM submit => Ops Manager ke paas; Customer abhi nahi dekh sakta, SMM khud approve nahi kar sakta
        $this->actingAs($this->team)->post("/calendars/{$calendar->id}/submit");
        $this->assertSame(CalendarStatus::Submitted, $calendar->fresh()->status);
        $this->actingAs($this->clientUser)->get("/calendars/{$calendar->id}")->assertForbidden();
        $this->actingAs($this->team)->post("/calendars/{$calendar->id}/review", ['action' => 'approved'])->assertForbidden();

        $ops = User::factory()->create(['role' => Role::Manager]);
        $this->actingAs($ops)->post("/calendars/{$calendar->id}/review", ['action' => 'approved']);
        $this->assertSame(CalendarStatus::ApproverApproved, $calendar->fresh()->status);
        $this->assertSame(1, $this->clientUser->notifications()->count());

        $this->actingAs($this->clientUser)->get("/calendars/{$calendar->id}")->assertOk()->assertSee('Diwali offer')->assertSee('LinkedIn Carousel, Instagram Reel');
        $this->actingAs($this->clientUser)->post("/calendars/{$calendar->id}/review", ['action' => 'approved']);
        $this->assertSame(CalendarStatus::ClientApproved, $calendar->fresh()->status);

        // Customer approve karte hi task auto-assign (Designer ko)
        $task = $calendar->items()->first()->task;
        $this->assertNotNull($task);
        $this->assertSame($this->approver->id, $task->assigned_to);

        // Approved ke baad edit band
        $this->actingAs($this->team)->get("/calendars/{$calendar->id}/items/create")->assertForbidden();
    }

    public function test_auto_assign_picks_least_busy_member_of_right_role(): void
    {
        $busy = $this->approver; // Graphic Designer
        $free = User::factory()->create(['role' => Role::GraphicDesigner]);
        $editor = User::factory()->create(['role' => Role::VideoEditor]);
        // $busy ke paas pehle se ek pending task
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

    public function test_revision_needs_comment_and_returns_to_draft(): void
    {
        $calendar = $this->draftWithItem();
        $this->submitAndOpsApprove($calendar);

        $this->actingAs($this->clientUser)->post("/calendars/{$calendar->id}/review", ['action' => 'revision'])
            ->assertSessionHasErrors('comment');
        $this->assertSame(CalendarStatus::ApproverApproved, $calendar->fresh()->status);

        $this->actingAs($this->clientUser)->post("/calendars/{$calendar->id}/review", ['action' => 'revision', 'comment' => 'Date badlo']);
        $this->assertSame(CalendarStatus::Draft, $calendar->fresh()->status);
        $this->actingAs($this->team)->get("/calendars/{$calendar->id}")->assertSee('Date badlo');
    }

    public function test_other_client_cannot_see_calendar(): void
    {
        $calendar = $this->draftWithItem();
        $calendar->update(['status' => CalendarStatus::ApproverApproved]);
        $other = User::factory()->create(['role' => Role::Client, 'client_id' => Client::create(['name' => 'Other'])->id]);

        $this->actingAs($other)->get("/calendars/{$calendar->id}")->assertForbidden();
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

    public function test_post_types_must_be_valid_options(): void
    {
        $this->actingAs($this->team)->post('/calendars', ['client_id' => $this->client->id, 'month' => '2026-11']);
        $calendar = Calendar::firstOrFail();

        $this->actingAs($this->team)->post("/calendars/{$calendar->id}/items", ['post_date' => '2026-11-03', 'post_types' => []])
            ->assertSessionHasErrors('post_types');
        $this->actingAs($this->team)->post("/calendars/{$calendar->id}/items", ['post_date' => '2026-11-03', 'post_types' => ['youtube_carousel']])
            ->assertSessionHasErrors('post_types.0');
    }
}
