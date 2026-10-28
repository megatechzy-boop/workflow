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
        $this->team = User::factory()->create(['role' => Role::TeamMember]);
        $this->approver = User::factory()->create(['role' => Role::Approver]);
        $this->clientUser = User::factory()->create(['role' => Role::Client, 'client_id' => $this->client->id]);
    }

    private function draftWithItem(): Calendar
    {
        $this->actingAs($this->team)->post('/calendars', ['client_id' => $this->client->id, 'month' => '2026-11']);
        $calendar = Calendar::firstOrFail();
        $this->actingAs($this->team)->post("/calendars/{$calendar->id}/items", [
            'post_date' => '2026-11-05', 'post_type' => 'carousel', 'creative_copy' => 'Diwali offer',
        ])->assertSessionHasNoErrors();

        return $calendar;
    }

    public function test_full_flow_team_to_approver_to_client(): void
    {
        $calendar = $this->draftWithItem();

        // Client draft nahi dekh sakta
        $this->actingAs($this->clientUser)->get("/calendars/{$calendar->id}")->assertForbidden();

        $this->actingAs($this->team)->post("/calendars/{$calendar->id}/submit");
        $this->assertSame(CalendarStatus::Submitted, $calendar->fresh()->status);

        // Client approver se pehle approve nahi kar sakta
        $this->actingAs($this->clientUser)->post("/calendars/{$calendar->id}/review", ['action' => 'approved'])->assertForbidden();

        $this->actingAs($this->approver)->post("/calendars/{$calendar->id}/review", ['action' => 'approved']);
        $this->assertSame(CalendarStatus::ApproverApproved, $calendar->fresh()->status);

        $this->actingAs($this->clientUser)->get("/calendars/{$calendar->id}")->assertOk()->assertSee('Diwali offer');
        $this->actingAs($this->clientUser)->post("/calendars/{$calendar->id}/review", ['action' => 'approved']);
        $this->assertSame(CalendarStatus::ClientApproved, $calendar->fresh()->status);

        // Approved ke baad edit band
        $this->actingAs($this->team)->get("/calendars/{$calendar->id}/items/create")->assertForbidden();
    }

    public function test_revision_needs_comment_and_returns_to_draft(): void
    {
        $calendar = $this->draftWithItem();
        $this->actingAs($this->team)->post("/calendars/{$calendar->id}/submit");

        $this->actingAs($this->approver)->post("/calendars/{$calendar->id}/review", ['action' => 'revision'])
            ->assertSessionHasErrors('comment');
        $this->assertSame(CalendarStatus::Submitted, $calendar->fresh()->status);

        $this->actingAs($this->approver)->post("/calendars/{$calendar->id}/review", ['action' => 'revision', 'comment' => 'Date badlo']);
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
        $this->actingAs($this->team)->post("/calendars/{$calendar->id}/items", ['post_date' => '2026-12-01', 'post_type' => 'post'])
            ->assertSessionHasErrors('post_date');
    }
}
