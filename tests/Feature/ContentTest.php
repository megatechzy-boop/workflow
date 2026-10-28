<?php

namespace Tests\Feature;

use App\Enums\CalendarStatus;
use App\Enums\ContentStatus;
use App\Enums\Role;
use App\Models\Calendar;
use App\Models\Client;
use App\Models\Content;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContentTest extends TestCase
{
    use RefreshDatabase;

    private Task $task;

    private User $member;

    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $manager = User::factory()->create(['role' => Role::Manager]);
        $this->member = User::factory()->create(['role' => Role::GraphicDesigner]);
        $this->approver = User::factory()->create(['role' => Role::SocialMediaManager]);
        $calendar = Calendar::create([
            'client_id' => Client::create(['name' => 'Acme'])->id, 'month' => '2026-11-01',
            'created_by' => $manager->id, 'status' => CalendarStatus::ClientApproved,
        ]);
        $item = $calendar->items()->create(['post_date' => '2026-11-10', 'post_types' => ['instagram_carousel', 'instagram_reel']]);
        $this->task = $item->task()->create(['assigned_to' => $this->member->id, 'assigned_by' => $manager->id, 'deadline' => '2026-11-08']);
    }

    private function images(int $n): array
    {
        return array_map(fn ($i) => UploadedFile::fake()->image("s$i.jpg"), range(1, $n));
    }

    private function uploadCarousel(int $slides = 3)
    {
        return $this->actingAs($this->member)->post("/tasks/{$this->task->id}/contents", [
            'post_type' => 'instagram_carousel', 'approver_id' => $this->approver->id, 'images' => $this->images($slides),
        ]);
    }

    public function test_carousel_needs_2_to_10_images(): void
    {
        $this->uploadCarousel(1)->assertSessionHasErrors('images');
        $this->uploadCarousel(11)->assertSessionHasErrors('images');
        $this->assertSame(0, Content::count());

        $this->uploadCarousel(3)->assertSessionHasNoErrors();
        $content = Content::firstOrFail();
        $this->assertSame(ContentStatus::Submitted, $content->status);
        $this->assertSame(3, $content->versions()->first()->media()->count());
    }

    public function test_reel_needs_video_and_cover(): void
    {
        $this->actingAs($this->member)->post("/tasks/{$this->task->id}/contents", [
            'post_type' => 'instagram_reel', 'approver_id' => $this->approver->id,
            'video' => UploadedFile::fake()->create('r.mp4', 500, 'video/mp4'),
        ])->assertSessionHasErrors('cover');

        $this->actingAs($this->member)->post("/tasks/{$this->task->id}/contents", [
            'post_type' => 'instagram_reel', 'approver_id' => $this->approver->id,
            'video' => UploadedFile::fake()->create('r.mp4', 500, 'video/mp4'), 'cover' => UploadedFile::fake()->image('c.jpg'),
        ])->assertSessionHasNoErrors();
    }

    public function test_post_type_must_belong_to_task_and_other_member_cannot_upload(): void
    {
        $this->actingAs($this->member)->post("/tasks/{$this->task->id}/contents", [
            'post_type' => 'linkedin_post', 'approver_id' => $this->approver->id, 'images' => $this->images(1),
        ])->assertSessionHasErrors('post_type');

        $other = User::factory()->create(['role' => Role::GraphicDesigner]);
        $this->actingAs($other)->post("/tasks/{$this->task->id}/contents", [
            'post_type' => 'instagram_carousel', 'approver_id' => $this->approver->id, 'images' => $this->images(2),
        ])->assertForbidden();
    }

    public function test_revision_then_resubmit_creates_v2_then_approve(): void
    {
        $this->uploadCarousel();
        $content = Content::firstOrFail();

        // only the chosen approver can review
        $this->actingAs(User::factory()->create(['role' => Role::SocialMediaManager]))
            ->post("/contents/{$content->id}/review", ['action' => 'approved'])->assertForbidden();

        $this->actingAs($this->approver)->post("/contents/{$content->id}/review", ['action' => 'revision'])
            ->assertSessionHasErrors('body');
        $this->actingAs($this->approver)->post("/contents/{$content->id}/review", ['action' => 'revision', 'body' => 'Make slide 2 text smaller']);
        $this->assertSame(ContentStatus::RevisionRequired, $content->fresh()->status);

        $this->actingAs($this->member)->post("/contents/{$content->id}/resubmit", ['images' => $this->images(3)])->assertSessionHasNoErrors();
        $content->refresh();
        $this->assertSame(ContentStatus::Resubmitted, $content->status);
        $this->assertSame(2, $content->current_version);
        $this->assertSame(2, $content->versions()->count());

        // SMM approve => with the Customer
        $customer = User::factory()->create(['role' => Role::Client, 'client_id' => $this->task->item->calendar->client_id]);
        $this->actingAs($customer)->get("/contents/{$content->id}")->assertForbidden(); // hidden before SMM approval
        $this->actingAs($this->approver)->post("/contents/{$content->id}/review", ['action' => 'approved']);
        $this->assertSame(ContentStatus::ClientReview, $content->fresh()->status);

        // another brand's customer cannot review; internal comments are hidden from the customer
        $other = User::factory()->create(['role' => Role::Client, 'client_id' => \App\Models\Client::create(['name' => 'Other'])->id]);
        $this->actingAs($other)->post("/contents/{$content->id}/customer-review", ['action' => 'approved'])->assertForbidden();
        $this->actingAs($customer)->get("/contents/{$content->id}")->assertOk()->assertDontSee('Make slide 2 text smaller');

        $this->actingAs($customer)->post("/contents/{$content->id}/customer-review", ['action' => 'revision'])->assertSessionHasErrors('body');
        $this->actingAs($customer)->post("/contents/{$content->id}/customer-review", ['action' => 'approved']);
        $this->assertSame(ContentStatus::Approved, $content->fresh()->status);

        // no resubmit after approval
        $this->actingAs($this->member)->post("/contents/{$content->id}/resubmit", ['images' => $this->images(2)])->assertForbidden();
    }

    public function test_slide_and_timestamp_comments(): void
    {
        $this->uploadCarousel();
        $content = Content::firstOrFail();

        $this->actingAs($this->approver)->post("/contents/{$content->id}/comments", ['body' => 'Bigger logo', 'version' => 1, 'slide' => 2]);
        $this->actingAs($this->approver)->post("/contents/{$content->id}/comments", ['body' => 'Cut yahan', 'version' => 1, 'at' => '1:15']);
        $this->actingAs($this->approver)->post("/contents/{$content->id}/comments", ['body' => 'x', 'version' => 1, 'at' => '1:75'])
            ->assertSessionHasErrors('at');

        $this->assertSame(2, $content->comments()->where('slide', 2)->count() + $content->comments()->where('at_seconds', 75)->count());
        $this->actingAs($this->member)->get("/contents/{$content->id}")->assertOk()->assertSee('Slide 2')->assertSee('1:15');
    }

    public function test_task_marked_submitted_only_when_all_types_uploaded_and_media_is_private(): void
    {
        $this->uploadCarousel();
        $this->assertSame('pending', $this->task->fresh()->status);

        $this->actingAs($this->member)->post("/tasks/{$this->task->id}/contents", [
            'post_type' => 'instagram_reel', 'approver_id' => $this->approver->id,
            'video' => UploadedFile::fake()->create('r.mp4', 500, 'video/mp4'), 'cover' => UploadedFile::fake()->image('c.jpg'),
        ]);
        $this->assertSame('submitted', $this->task->fresh()->status);
        $this->assertNotNull($this->task->fresh()->submitted_at);

        $mediaId = Content::first()->versions()->first()->media()->first()->id;
        $this->actingAs(User::factory()->create(['role' => Role::Client]))->get("/media/{$mediaId}")->assertForbidden();
        $this->actingAs($this->member)->get("/media/{$mediaId}")->assertOk();
    }

    public function test_download_has_clean_name_and_respects_access(): void
    {
        $this->uploadCarousel(2);
        $content = Content::firstOrFail();
        $media = $content->versions()->first()->media()->orderBy('sort_order')->get();

        $this->actingAs($this->approver)->get("/media/{$media[1]->id}/download")
            ->assertOk()
            ->assertDownload('acme-2026-11-10-instagram-carousel-slide-2.jpg');

        // Customer cannot download before SMM approval
        $customer = User::factory()->create(['role' => Role::Client, 'client_id' => $this->task->item->calendar->client_id]);
        $this->actingAs($customer)->get("/media/{$media[0]->id}/download")->assertForbidden();
        $this->actingAs($this->approver)->get("/contents/{$content->id}")->assertSee('Download all (2)')->assertSee('PDF download (LinkedIn)');
    }
}
