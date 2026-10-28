<?php

namespace Tests\Feature;

use App\Enums\CalendarStatus;
use App\Enums\Role;
use App\Models\Calendar;
use App\Models\Client;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    private Calendar $calendar;

    private User $manager;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-28 10:00:00');
        $this->manager = User::factory()->create(['role' => Role::Manager]);
        $this->member = User::factory()->create(['role' => Role::GraphicDesigner]);
        $this->calendar = Calendar::create([
            'client_id' => Client::create(['name' => 'Acme'])->id,
            'month' => '2026-11-01',
            'created_by' => $this->manager->id,
            'status' => CalendarStatus::ClientApproved,
        ]);
    }

    private function item(string $date)
    {
        return $this->calendar->items()->create(['post_date' => $date, 'post_types' => ['instagram_post']]);
    }

    public function test_manager_assigns_and_deadline_is_two_days_before_post_date(): void
    {
        $item = $this->item('2026-11-10');

        $this->actingAs($this->manager)->post("/calendars/{$this->calendar->id}/assign", ['assign' => [$item->id => $this->member->id]])
            ->assertSessionHasNoErrors();

        $task = Task::firstOrFail();
        $this->assertSame($this->member->id, $task->assigned_to);
        $this->assertSame('2026-11-08', $task->deadline->toDateString());
    }

    public function test_deadline_never_in_the_past(): void
    {
        $this->assertSame('2026-10-28', Task::deadlineFor(Carbon::parse('2026-10-29'))->toDateString());
    }

    public function test_cannot_assign_before_client_approval_or_as_non_manager(): void
    {
        $item = $this->item('2026-11-10');
        $payload = ['assign' => [$item->id => $this->member->id]];

        $this->actingAs($this->member)->post("/calendars/{$this->calendar->id}/assign", $payload)->assertForbidden();

        $this->calendar->update(['status' => CalendarStatus::ApproverApproved]);
        $this->actingAs($this->manager)->post("/calendars/{$this->calendar->id}/assign", $payload)->assertForbidden();
        $this->assertSame(0, Task::count());
    }

    public function test_only_team_members_can_be_assigned(): void
    {
        $item = $this->item('2026-11-10');
        $client = User::factory()->create(['role' => Role::Client]);

        $this->actingAs($this->manager)->post("/calendars/{$this->calendar->id}/assign", ['assign' => [$item->id => $client->id]])
            ->assertSessionHasErrors();
    }

    public function test_member_sees_only_own_tasks(): void
    {
        $other = User::factory()->create(['role' => Role::GraphicDesigner]);
        $a = $this->item('2026-11-10');
        $b = $this->item('2026-11-12');
        $a->update(['creative_copy' => 'My task']);
        $b->update(['creative_copy' => 'Other task']);
        $this->actingAs($this->manager)->post("/calendars/{$this->calendar->id}/assign", ['assign' => [$a->id => $this->member->id, $b->id => $other->id]]);

        $this->actingAs($this->member)->get('/tasks')->assertOk()->assertSee('My task')->assertDontSee('Other task');
        $this->actingAs($this->manager)->get('/tasks')->assertSee('My task')->assertSee('Other task');
        $this->actingAs(User::factory()->create(['role' => Role::Client]))->get('/tasks')->assertForbidden();
    }

    public function test_reel_goes_to_video_editor_and_post_to_designer(): void
    {
        $editor = User::factory()->create(['role' => Role::VideoEditor]);
        $post = $this->item('2026-11-10');
        $reel = $this->calendar->items()->create(['post_date' => '2026-11-12', 'post_types' => ['instagram_reel']]);

        $this->actingAs($this->manager)->post("/calendars/{$this->calendar->id}/assign", ['assign' => [$post->id => $editor->id]])
            ->assertSessionHasErrors("assign.{$post->id}");
        $this->actingAs($this->manager)->post("/calendars/{$this->calendar->id}/assign", ['assign' => [$reel->id => $this->member->id]])
            ->assertSessionHasErrors("assign.{$reel->id}");
        $this->assertSame(0, Task::count());

        $smm = User::factory()->create(['role' => Role::SocialMediaManager]);
        $this->actingAs($smm)->post("/calendars/{$this->calendar->id}/assign", ['assign' => [$post->id => $this->member->id, $reel->id => $editor->id]])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, Task::count());
    }
}
