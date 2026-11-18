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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Changes after approval: "Revision" (SMM / Admin / Customer, full round) and "Send back for changes" (SMM / Admin, SMM approval final) */
class ContentChangeTest extends TestCase
{
    use RefreshDatabase;

    private User $designer;

    private User $smm;

    private User $customer;

    private Content $content;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $manager = User::factory()->create(['role' => Role::Manager]);
        $this->designer = User::factory()->create(['role' => Role::GraphicDesigner]);
        $this->smm = User::factory()->create(['role' => Role::SocialMediaManager]);
        $client = Client::create(['name' => 'Acme']);
        $this->customer = User::factory()->create(['role' => Role::Client, 'client_id' => $client->id]);
        $calendar = Calendar::create(['client_id' => $client->id, 'month' => '2026-11-01', 'created_by' => $manager->id, 'status' => CalendarStatus::ClientApproved]);
        $item = $calendar->items()->create(['post_date' => '2026-11-10', 'post_types' => ['instagram_carousel']]);
        $task = $item->task()->create(['assigned_to' => $this->designer->id, 'assigned_by' => $manager->id, 'deadline' => '2026-11-08']);

        $this->actingAs($this->designer)->post("/tasks/{$task->id}/contents", ['post_type' => 'carousel', 'approver_id' => $this->smm->id, 'images' => $this->images()])
            ->assertSessionHasNoErrors();
        $this->content = Content::sole();
        $this->actingAs($this->smm)->post(route('contents.review', $this->content), ['action' => 'approved']);
        $this->actingAs($this->customer)->post(route('contents.customerReview', $this->content), ['action' => 'approved']);
        $this->content->refresh()->update(['status' => ContentStatus::Scheduled]);
    }

    private function images(): array
    {
        return [UploadedFile::fake()->image('s1.jpg'), UploadedFile::fake()->image('s2.jpg')];
    }

    private function metaPost(string $status): MetaPost
    {
        $page = MetaPage::firstOrCreate(['page_id' => '111'], ['name' => 'Acme', 'access_token' => 't']);

        return MetaPost::create(['content_id' => $this->content->id, 'meta_page_id' => $page->id, 'platform' => 'instagram', 'status' => $status, 'publish_at' => now()->addDay()]);
    }

    public function test_send_back_for_a_small_fix_cancels_the_schedule_and_skips_the_customer(): void
    {
        $this->metaPost('queued');
        $this->actingAs($this->smm)->post(route('contents.sendBack', $this->content), ['body' => 'Logo missing'])->assertSessionHas('status');

        $this->content->refresh();
        $this->assertSame([ContentStatus::RevisionRequired, true], [$this->content->status, $this->content->minor_fix]);
        $this->assertSame(0, MetaPost::count()); // the old files will not go out
        $this->assertTrue($this->designer->notifications()->where('data->title', 'Small fix needed')->exists());
        $this->assertTrue($this->content->comments()->where('action', 'small_fix')->sole()->isChecklistItem());

        $this->actingAs($this->designer)->post(route('contents.resubmit', $this->content), ['images' => $this->images()])->assertSessionHasNoErrors();
        $this->actingAs($this->smm)->get(route('contents.show', $this->content))->assertSee('your approval is final');
        $this->post(route('contents.review', $this->content), ['action' => 'approved']);

        $this->content->refresh();
        $this->assertSame([ContentStatus::Approved, false, 2], [$this->content->status, $this->content->minor_fix, $this->content->current_version]);
    }

    public function test_revision_after_approval_goes_to_the_customer_again(): void
    {
        $this->actingAs($this->customer)->post(route('contents.reopen', $this->content), ['body' => 'Change the photo'])
            ->assertRedirect(route('contents.index'));
        $this->content->refresh();
        $this->assertSame([ContentStatus::RevisionRequired, false], [$this->content->status, $this->content->minor_fix]);
        $this->assertSame('customer_revision', $this->content->comments()->reorder()->latest('id')->value('action'));

        $this->actingAs($this->designer)->post(route('contents.resubmit', $this->content), ['images' => $this->images()]);
        $this->actingAs($this->smm)->post(route('contents.review', $this->content), ['action' => 'approved']);
        $this->assertSame(ContentStatus::ClientReview, $this->content->refresh()->status);

        // the SMM can ask for a revision too
        $this->actingAs($this->customer)->post(route('contents.customerReview', $this->content), ['action' => 'approved']);
        $this->actingAs($this->smm)->post(route('contents.reopen', $this->content), ['body' => 'New offer price'])->assertSessionHas('status');
        $this->assertSame(ContentStatus::RevisionRequired, $this->content->refresh()->status);
    }

    public function test_who_may_send_back(): void
    {
        $this->actingAs($this->designer)->get(route('contents.show', $this->content))->assertOk()->assertDontSee('Need a change?');
        $this->post(route('contents.sendBack', $this->content), ['body' => 'x'])->assertForbidden();
        $this->post(route('contents.reopen', $this->content), ['body' => 'x'])->assertForbidden();

        $this->actingAs($this->customer)->get(route('contents.show', $this->content))->assertSee('Request revision')->assertDontSee('Send back for changes');
        $this->post(route('contents.sendBack', $this->content), ['body' => 'x'])->assertForbidden();

        $this->actingAs($this->smm)->get(route('contents.show', $this->content))->assertSee('Send back for changes');
        $this->post(route('contents.sendBack', $this->content), [])->assertSessionHasErrors('body');

        // already live on Instagram: no more changes
        $this->metaPost('done');
        $this->post(route('contents.sendBack', $this->content), ['body' => 'x'])->assertForbidden();
        $this->actingAs($this->customer)->post(route('contents.reopen', $this->content), ['body' => 'x'])->assertForbidden();
        $this->assertSame(ContentStatus::Scheduled, $this->content->refresh()->status);
    }
}
