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
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MetaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.meta.app_id' => '123', 'services.meta.app_secret' => 'secret']);
        Storage::fake('local');
        $this->admin = User::factory()->create(['role' => Role::Admin]);
    }

    private function approvedPost(array $postTypes = ['instagram_post', 'facebook_post'], ?MetaPage $page = null): Content
    {
        $client = Client::create(['name' => 'Acme', 'meta_page_id' => $page?->id]);
        $calendar = Calendar::create(['client_id' => $client->id, 'month' => '2026-11-01', 'created_by' => $this->admin->id, 'status' => CalendarStatus::ClientApproved]);
        $item = $calendar->items()->create(['post_date' => '2026-11-05', 'post_types' => $postTypes]);
        $task = Task::forceCreate(['calendar_item_id' => $item->id, 'assigned_to' => $this->admin->id, 'assigned_by' => $this->admin->id, 'deadline' => '2026-11-03']);
        $content = Content::forceCreate(['task_id' => $task->id, 'post_type' => 'post', 'approver_id' => $this->admin->id, 'status' => ContentStatus::Approved, 'current_version' => 1]);
        $version = $content->versions()->create(['number' => 1, 'caption' => 'Diwali offer', 'hashtags' => '#yoga', 'submitted_by' => $this->admin->id]);
        Storage::disk('local')->put('contents/1/v1/a.jpg', 'x');
        $version->media()->create(['kind' => 'image', 'path' => 'contents/1/v1/a.jpg', 'original_name' => 'a.jpg', 'sort_order' => 1]);

        return $content;
    }

    public function test_admin_connects_facebook_and_pages_are_saved(): void
    {
        Http::fake([
            '*/oauth/access_token*' => Http::response(['access_token' => 'user-token']),
            '*/me/accounts*' => Http::response(['data' => [
                ['id' => '111', 'name' => 'Acme Yoga', 'access_token' => 'page-token', 'instagram_business_account' => ['id' => '999', 'username' => 'acmeyoga']],
            ]]),
        ]);

        $redirect = $this->actingAs($this->admin)->get(route('admin.meta.connect'))->headers->get('Location');
        $this->assertStringContainsString('instagram_content_publish', $redirect);
        parse_str(parse_url($redirect, PHP_URL_QUERY), $query);

        $this->actingAs($this->admin)->get(route('admin.meta.callback', ['code' => 'abc', 'state' => $query['state']]))
            ->assertSessionHas('status', '1 Page(s) connected. Now link each client to its Page (Clients > Edit).');

        $page = MetaPage::sole();
        $this->assertSame('acmeyoga', $page->ig_username);
        $this->assertSame('page-token', $page->access_token);
        $this->assertNotSame('page-token', \DB::table('meta_pages')->value('access_token')); // stored encrypted
    }

    public function test_callback_rejects_wrong_state_and_non_admins(): void
    {
        $this->actingAs($this->admin)->get(route('admin.meta.callback', ['code' => 'abc', 'state' => 'forged']))
            ->assertSessionHas('status', 'Facebook connection was cancelled.');
        $this->actingAs(User::factory()->create(['role' => Role::Manager]))->get(route('admin.meta.index'))->assertForbidden();
    }

    public function test_publish_needs_a_linked_page(): void
    {
        $content = $this->approvedPost();
        $this->actingAs($this->admin)->post(route('contents.meta', $content))
            ->assertSessionHas('status', 'Link this client to a Facebook Page first (Clients > Edit).');
        $this->assertSame(0, MetaPost::count());
    }

    public function test_queued_posts_go_to_facebook_and_instagram_then_content_is_published(): void
    {
        $page = MetaPage::create(['page_id' => '111', 'name' => 'Acme Yoga', 'access_token' => 'page-token', 'ig_user_id' => '999', 'ig_username' => 'acmeyoga']);
        $content = $this->approvedPost(page: $page);
        Http::fake([
            '*/111/photos' => Http::response(['id' => 'photo1', 'post_id' => '111_fb1']),
            '*/999/media' => Http::response(['id' => 'container1']),
            '*/container1*' => Http::response(['status_code' => 'FINISHED']),
            '*/999/media_publish' => Http::response(['id' => 'ig1']),
        ]);

        $this->actingAs($this->admin)->post(route('contents.meta', $content))->assertSessionHas('status', 'Queued. It will be posted within a minute or two.');
        $this->assertSame(2, MetaPost::where('status', 'queued')->count());

        $this->artisan('meta:publish')->assertSuccessful();

        $this->assertSame(['111_fb1', 'ig1'], MetaPost::orderBy('platform')->pluck('post_id')->all());
        $this->assertSame(ContentStatus::Published, $content->fresh()->status);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/999/media')
            && $r['caption'] === "Diwali offer\n\n#yoga" && str_contains($r['image_url'], 'signature='));
    }

    public function test_failed_post_shows_error_and_can_be_retried(): void
    {
        $page = MetaPage::create(['page_id' => '111', 'name' => 'Acme Yoga', 'access_token' => 'page-token']);
        $content = $this->approvedPost(['facebook_post'], $page);
        Http::fake(['*/111/photos' => Http::response(['error' => ['message' => 'Invalid image']], 400)]);

        $this->actingAs($this->admin)->post(route('contents.meta', $content));
        $this->artisan('meta:publish');
        $this->assertSame('Invalid image', MetaPost::sole()->error);
        $this->actingAs($this->admin)->get(route('contents.show', $content))->assertSee('Try again');

        $this->actingAs($this->admin)->post(route('contents.meta', $content));
        $this->assertSame('queued', MetaPost::sole()->status);
    }

    public function test_media_link_needs_a_valid_signature(): void
    {
        $content = $this->approvedPost();
        $media = $content->versions()->first()->media()->first();

        $this->get(route('meta.media', $media))->assertForbidden();
        $this->get(\URL::temporarySignedRoute('meta.media', now()->addHour(), ['media' => $media->id]))->assertOk();
    }
}
