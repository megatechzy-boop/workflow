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
            'post_type' => 'carousel', 'approver_id' => $this->approver->id, 'images' => $this->images($slides),
        ]);
    }

    public function test_upload_with_progress_bar_gets_json_redirect_and_keeps_errors(): void
    {
        $send = fn (int $slides) => $this->actingAs($this->member)->withHeader('X-Upload-Progress', '1')
            ->post("/tasks/{$this->task->id}/contents", ['post_type' => 'carousel', 'approver_id' => $this->approver->id, 'images' => $this->images($slides)]);

        // validation error: JSON redirect back, errors still waiting in the session for that page
        $send(1)->assertOk()->assertJsonStructure(['redirect'])->assertSessionHasErrors('images');

        $send(3)->assertOk()->assertJsonPath('redirect', route('contents.show', Content::firstOrFail()));
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

    public function test_reel_needs_video(): void
    {
        // Reels belong to the Video Editor
        $editor = User::factory()->create(['role' => Role::VideoEditor]);
        $this->task->update(['assigned_to' => $editor->id]);

        // Video is required (cover is optional)
        $this->actingAs($editor)->post("/tasks/{$this->task->id}/contents", [
            'post_type' => 'reel', 'cover' => UploadedFile::fake()->image('c.jpg'),
        ])->assertSessionHasErrors('video');

        $this->actingAs($editor)->post("/tasks/{$this->task->id}/contents", [
            'post_type' => 'reel',
            'video' => UploadedFile::fake()->create('r.mp4', 500, 'video/mp4'), 'cover' => UploadedFile::fake()->image('c.jpg'),
        ])->assertSessionHasNoErrors();
        $this->assertSame('submitted', $this->task->fresh()->status); // editor's only format = reel
    }

    public function test_post_type_must_belong_to_task_and_other_member_cannot_upload(): void
    {
        $this->actingAs($this->member)->post("/tasks/{$this->task->id}/contents", [
            'post_type' => 'post', 'approver_id' => $this->approver->id, 'images' => $this->images(1),
        ])->assertSessionHasErrors('post_type');

        $other = User::factory()->create(['role' => Role::GraphicDesigner]);
        $this->actingAs($other)->post("/tasks/{$this->task->id}/contents", [
            'post_type' => 'carousel', 'approver_id' => $this->approver->id, 'images' => $this->images(2),
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

        // a second tap (or an old tab): back to the list with a popup, nothing changes, no 403 page
        $this->actingAs($customer)->post("/contents/{$content->id}/customer-review", ['action' => 'revision', 'body' => 'again'])
            ->assertRedirect(route('contents.index'))->assertSessionHas('popup');
        $this->assertSame(ContentStatus::Approved, $content->fresh()->status);
        $this->actingAs($other)->post("/contents/{$content->id}/customer-review", ['action' => 'approved'])->assertForbidden(); // other brand: still 403

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

    public function test_task_submitted_after_assignee_formats_and_media_is_private(): void
    {
        $this->assertSame('pending', $this->task->fresh()->status);
        $this->uploadCarousel(); // designer's only format on this Carousel + Reel row
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
            ->assertDownload('acme-2026-11-10-carousel-slide-2.jpg');

        // Customer cannot download before SMM approval
        $customer = User::factory()->create(['role' => Role::Client, 'client_id' => $this->task->item->calendar->client_id]);
        $this->actingAs($customer)->get("/media/{$media[0]->id}/download")->assertForbidden();
        $this->actingAs($this->approver)->get("/contents/{$content->id}")->assertSee('Download all (2)')->assertSee('PDF download (LinkedIn)');
    }

    public function test_one_upload_covers_all_platforms_of_a_format(): void
    {
        // Row: Instagram + Facebook + LinkedIn post => one "post" upload only
        $item = $this->task->item;
        $item->update(['post_types' => ['instagram_post', 'facebook_post', 'linkedin_post']]);

        $this->actingAs($this->member)->get("/tasks/{$this->task->id}")
            ->assertSee('Post · Instagram, Facebook, LinkedIn');

        $this->actingAs($this->member)->post("/tasks/{$this->task->id}/contents", [
            'post_type' => 'post', 'approver_id' => $this->approver->id, 'images' => $this->images(1),
        ])->assertSessionHasNoErrors();

        // Format done => task submitted, second upload of the same format refused
        $this->assertSame('submitted', $this->task->fresh()->status);
        $this->actingAs($this->member)->post("/tasks/{$this->task->id}/contents", [
            'post_type' => 'post', 'approver_id' => $this->approver->id, 'images' => $this->images(1),
        ])->assertSessionHasErrors('post_type');
        $this->assertSame(1, Content::count());
    }

    public function test_designer_uploads_files_only_and_smm_sets_caption(): void
    {
        // No approver chosen by the designer: the SMM is picked automatically
        $this->actingAs($this->member)->post("/tasks/{$this->task->id}/contents", [
            'post_type' => 'carousel', 'images' => $this->images(2), 'caption' => 'designer caption',
        ])->assertSessionHasNoErrors();
        $content = Content::firstOrFail();
        $this->assertSame($this->approver->id, $content->approver_id);
        $this->assertNull($content->versions()->first()->caption); // designer cannot set it

        $this->actingAs($this->member)->get("/tasks/{$this->task->id}")->assertDontSee('Choose approver');
        $this->actingAs($this->member)->post("/contents/{$content->id}/caption", ['caption' => 'x'])->assertForbidden();

        $this->actingAs($this->approver)->post("/contents/{$content->id}/caption", ['caption' => 'Final caption', 'hashtags' => '#acme'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Final caption', $content->versions()->first()->caption);

        // Caption carries over to V2 after a revision
        $this->actingAs($this->approver)->post("/contents/{$content->id}/review", ['action' => 'revision', 'body' => 'Fix colors']);
        $this->actingAs($this->member)->post("/contents/{$content->id}/resubmit", ['images' => $this->images(2)]);
        $this->assertSame('#acme', $content->versions()->where('number', 2)->value('hashtags'));
    }

    public function test_designer_sees_and_uploads_only_post_formats(): void
    {
        // Row has Carousel + Reel, task belongs to the Graphic Designer
        $this->actingAs($this->member)->get("/tasks/{$this->task->id}")
            ->assertSee('Carousel · Instagram')->assertDontSee('Reel · Instagram');

        // Reel is not the designer's format => refused
        $this->actingAs($this->member)->post("/tasks/{$this->task->id}/contents", [
            'post_type' => 'reel',
            'video' => UploadedFile::fake()->create('r.mp4', 500, 'video/mp4'), 'cover' => UploadedFile::fake()->image('c.jpg'),
        ])->assertSessionHasErrors('post_type');

        // Carousel done => the designer's task is submitted
        $this->uploadCarousel(2)->assertSessionHasNoErrors();
        $this->assertSame('submitted', $this->task->fresh()->status);
    }

    public function test_reel_cover_is_optional(): void
    {
        $editor = User::factory()->create(['role' => Role::VideoEditor]);
        $this->task->update(['assigned_to' => $editor->id]);

        $this->actingAs($editor)->post("/tasks/{$this->task->id}/contents", [
            'post_type' => 'reel', 'video' => UploadedFile::fake()->create('r.mp4', 500, 'video/mp4'),
        ])->assertSessionHasNoErrors();
        $this->assertSame(['video'], Content::first()->versions()->first()->media()->pluck('kind')->all());
    }

    public function test_replace_wrong_files_before_review_only(): void
    {
        $this->uploadCarousel(2);
        $content = Content::firstOrFail();
        $oldPath = $content->versions()->first()->media()->first()->path;

        // Another designer cannot replace
        $this->actingAs(User::factory()->create(['role' => Role::GraphicDesigner]))
            ->post("/contents/{$content->id}/replace", ['images' => $this->images(3)])->assertForbidden();

        $this->actingAs($this->member)->post("/contents/{$content->id}/replace", ['images' => $this->images(3)])->assertSessionHasNoErrors();
        $content->refresh();
        $this->assertSame(1, $content->current_version);                       // still V1
        $this->assertSame(3, $content->versions()->first()->media()->count()); // new files
        Storage::disk('local')->assertMissing($oldPath);                        // old file gone
        $this->assertContains('Files replaced', $this->approver->notifications->pluck('data.title'));

        // After the SMM reviews, replacing is closed
        $this->actingAs($this->approver)->post("/contents/{$content->id}/review", ['action' => 'approved']);
        $this->actingAs($this->member)->post("/contents/{$content->id}/replace", ['images' => $this->images(2)])->assertForbidden();
    }
}
