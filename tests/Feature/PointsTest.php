<?php

namespace Tests\Feature;

use App\Enums\CalendarStatus;
use App\Enums\Role;
use App\Models\Calendar;
use App\Models\Client;
use App\Models\Content;
use App\Models\PointEntry;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PointsTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->member = User::factory()->create(['role' => Role::GraphicDesigner]);
        $this->approver = User::factory()->create(['role' => Role::SocialMediaManager]);
    }

    private function task(string $deadline): Task
    {
        $manager = User::factory()->create(['role' => Role::Manager]);
        $calendar = Calendar::create([
            'client_id' => Client::create(['name' => 'Acme'])->id, 'month' => '2026-11-01',
            'created_by' => $manager->id, 'status' => CalendarStatus::ClientApproved,
        ]);
        $item = $calendar->items()->create(['post_date' => '2026-11-10', 'post_types' => ['instagram_post']]);

        return $item->task()->create(['assigned_to' => $this->member->id, 'assigned_by' => $manager->id, 'deadline' => $deadline]);
    }

    private function upload(Task $task): Content
    {
        $this->actingAs($this->member)->post("/tasks/{$task->id}/contents", [
            'post_type' => 'instagram_post', 'approver_id' => $this->approver->id, 'images' => [UploadedFile::fake()->image('a.jpg')],
        ]);

        return Content::latest('id')->firstOrFail();
    }

    public function test_on_time_submit_plus_first_approve(): void
    {
        Carbon::setTestNow('2026-11-08 18:00'); // deadline day = on time
        $content = $this->upload($this->task('2026-11-08'));
        $this->actingAs($this->approver)->post("/contents/{$content->id}/review", ['action' => 'approved']);
        $this->assertSame(10, (int) PointEntry::where('user_id', $this->member->id)->sum('points')); // bonus only after the Customer

        $customer = User::factory()->create(['role' => Role::Client, 'client_id' => $content->task->item->calendar->client_id]);
        $this->actingAs($customer)->post("/contents/{$content->id}/customer-review", ['action' => 'approved']);

        $this->assertSame(10 + 5, (int) PointEntry::where('user_id', $this->member->id)->sum('points'));
    }

    public function test_late_submit_and_no_bonus_after_revision(): void
    {
        Carbon::setTestNow('2026-11-09 09:00'); // one day after the deadline
        $content = $this->upload($this->task('2026-11-08'));
        $this->actingAs($this->approver)->post("/contents/{$content->id}/review", ['action' => 'revision', 'body' => 'Fix']);
        $this->actingAs($this->member)->post("/contents/{$content->id}/resubmit", ['images' => [UploadedFile::fake()->image('b.jpg')]]);
        $this->actingAs($this->approver)->post("/contents/{$content->id}/review", ['action' => 'approved']);

        $this->assertSame(-5, (int) PointEntry::where('user_id', $this->member->id)->sum('points'));
    }

    public function test_leaderboard_shows_totals_and_blocks_clients(): void
    {
        Carbon::setTestNow('2026-11-08 10:00');
        $this->upload($this->task('2026-11-08'));

        $this->actingAs($this->member)->get('/leaderboard?month=2026-11')->assertOk()->assertSee($this->member->name)->assertSee('Submitted on time');
        $this->actingAs(User::factory()->create(['role' => Role::Client]))->get('/leaderboard')->assertForbidden();
    }
}
