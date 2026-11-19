<?php

namespace Tests\Feature;

use App\Enums\CalendarStatus;
use App\Enums\ContentStatus;
use App\Enums\Role;
use App\Models\Calendar;
use App\Models\Client;
use App\Models\Content;
use App\Models\MetaPage;
use App\Models\MetaPost;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CalendarDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $smm;

    private Calendar $calendar;

    private Content $content;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->admin = User::factory()->create(['role' => Role::Admin]);
        $this->smm = User::factory()->create(['role' => Role::SocialMediaManager]);
        $this->calendar = Calendar::create(['client_id' => Client::create(['name' => 'Acme'])->id, 'month' => '2026-12-01', 'status' => CalendarStatus::ClientApproved, 'created_by' => $this->smm->id]);
        $item = $this->calendar->items()->create(['post_date' => '2026-12-05', 'post_types' => ['instagram_post']]);
        $task = Task::forceCreate(['calendar_item_id' => $item->id, 'assigned_to' => $this->smm->id, 'assigned_by' => $this->smm->id, 'deadline' => '2026-12-03']);
        $this->content = Content::forceCreate(['task_id' => $task->id, 'post_type' => 'post', 'approver_id' => $this->smm->id, 'status' => ContentStatus::Scheduled, 'current_version' => 1]);
        $version = $this->content->versions()->create(['number' => 1, 'submitted_by' => $this->smm->id]);
        Storage::disk('local')->put('contents/a.jpg', 'x');
        $version->media()->create(['kind' => 'image', 'path' => 'contents/a.jpg', 'original_name' => 'a.jpg', 'sort_order' => 1]);
    }

    private function metaPost(string $status): void
    {
        $page = MetaPage::create(['page_id' => '1', 'name' => 'Acme', 'access_token' => 't']);
        MetaPost::create(['content_id' => $this->content->id, 'meta_page_id' => $page->id, 'platform' => 'instagram', 'status' => $status, 'publish_at' => now()->addDay()]);
    }

    public function test_admin_deletes_an_approved_calendar_with_its_posts_files_and_schedule(): void
    {
        $this->metaPost('queued');
        $this->actingAs($this->smm)->delete(route('calendars.destroy', $this->calendar))->assertForbidden(); // not a draft: Admin only

        $this->actingAs($this->admin)->get(route('calendars.show', $this->calendar))->assertSee('Delete calendar')->assertSee('cannot be undone');
        $this->delete(route('calendars.destroy', $this->calendar))->assertRedirect(route('calendars.index'));
        $this->assertSame([0, 0, 0], [Calendar::count(), Content::count(), MetaPost::count()]);
        Storage::disk('local')->assertMissing('contents/a.jpg');
    }

    public function test_a_published_post_blocks_the_delete(): void
    {
        $this->metaPost('done');
        $this->actingAs($this->admin)->get(route('calendars.show', $this->calendar))->assertDontSee('Delete calendar')->assertSee('already published');
        $this->delete(route('calendars.destroy', $this->calendar))->assertForbidden();
        $this->assertSame(1, Calendar::count());
    }

    public function test_draft_is_still_deleted_by_its_creator(): void
    {
        $this->calendar->update(['status' => CalendarStatus::Draft]);
        $this->actingAs($this->smm)->delete(route('calendars.destroy', $this->calendar))->assertRedirect(route('calendars.index'));
        $this->assertSame(0, Calendar::count());
    }
}
