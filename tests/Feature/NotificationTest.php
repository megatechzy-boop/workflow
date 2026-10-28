<?php

namespace Tests\Feature;

use App\Enums\CalendarStatus;
use App\Enums\Role;
use App\Models\Calendar;
use App\Models\Client;
use App\Models\Content;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private Calendar $calendar;

    private User $manager;

    private User $member;

    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->manager = User::factory()->create(['role' => Role::Manager, 'name' => 'Ravi Manager']);
        $this->member = User::factory()->create(['role' => Role::GraphicDesigner, 'name' => 'Priya Sharma']);
        $this->approver = User::factory()->create(['role' => Role::SocialMediaManager, 'name' => 'Amit Approver']);
        $this->calendar = Calendar::create([
            'client_id' => Client::create(['name' => 'Acme'])->id, 'month' => '2026-11-01',
            'created_by' => $this->manager->id, 'status' => CalendarStatus::ClientApproved,
        ]);
    }

    private function assignAndUpload(): Content
    {
        $item = $this->calendar->items()->create(['post_date' => '2026-11-10', 'post_types' => ['instagram_post']]);
        $this->actingAs($this->manager)->post("/calendars/{$this->calendar->id}/assign", ['assign' => [$item->id => $this->member->id]]);
        $this->actingAs($this->member)->post("/tasks/{$item->task->id}/contents", [
            'post_type' => 'post', 'approver_id' => $this->approver->id, 'images' => [UploadedFile::fake()->image('a.jpg')],
        ]);

        return Content::firstOrFail();
    }

    public function test_assign_upload_and_review_notify_the_right_people(): void
    {
        $content = $this->assignAndUpload();

        $this->assertSame('New task assigned', $this->member->notifications()->first()->data['title']);
        $this->assertSame('Content needs approval', $this->approver->notifications()->first()->data['title']);

        $this->actingAs($this->approver)->post("/contents/{$content->id}/review", ['action' => 'revision', 'body' => 'Make the logo smaller']);
        $this->assertContains('Content: Revision required', $this->member->fresh()->notifications->pluck('data.title'));
    }

    public function test_mention_notifies_by_name_without_spaces(): void
    {
        $content = $this->assignAndUpload();

        $this->actingAs($this->approver)->post("/contents/{$content->id}/comments", ['body' => 'Look @RaviManager', 'version' => 1]);

        $this->assertSame('You were mentioned', $this->manager->notifications()->first()->data['title']);
        // the approver does not notify themselves
        $this->assertSame(1, $this->approver->notifications()->count());
    }

    public function test_broken_mail_does_not_break_the_request(): void
    {
        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', '127.0.0.1');
        Config::set('mail.mailers.smtp.port', 1); // no server

        $this->assignAndUpload();

        $this->assertSame(1, Content::count());
        $this->assertSame(1, $this->approver->notifications()->count()); // in-app is still stored
    }

    public function test_member_marks_revision_comment_fixed_and_bell_page_works(): void
    {
        $content = $this->assignAndUpload();
        $this->actingAs($this->approver)->post("/contents/{$content->id}/review", ['action' => 'revision', 'body' => 'Make the text bigger']);
        $comment = $content->comments()->where('action', 'revision')->firstOrFail();

        $this->actingAs($this->approver)->post("/contents/{$content->id}/comments/{$comment->id}/fixed")->assertForbidden();
        $this->actingAs($this->member)->post("/contents/{$content->id}/comments/{$comment->id}/fixed");
        $this->assertNotNull($comment->fresh()->fixed_at);
        $this->actingAs($this->member)->get("/contents/{$content->id}")->assertSee('Fixed ·', false);

        $id = $this->member->notifications()->first()->id;
        $this->actingAs($this->member)->get("/notifications/{$id}")->assertRedirect();
        $this->assertNotNull($this->member->notifications()->find($id)->read_at);
        $this->actingAs($this->approver)->get("/notifications/{$id}")->assertNotFound(); // cannot open someone else's notification
    }
}
