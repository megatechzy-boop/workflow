<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Client;
use App\Models\GbpLocation;
use App\Models\GbpPost;
use App\Models\GoogleAccount;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use App\Support\GbpPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GbpPostTest extends TestCase
{
    use RefreshDatabase;

    private User $smm;

    private Client $client;

    private GbpLocation $location;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->smm = User::factory()->create(['role' => Role::SocialMediaManager]);
        GoogleAccount::create(['access_token' => 'token', 'refresh_token' => 'r', 'expires_at' => now()->addHour()]);
        $this->client = Client::create(['name' => 'Acme']);
        $this->location = GbpLocation::create(['client_id' => $this->client->id, 'account_name' => 'accounts/11', 'location_name' => 'locations/21',
            'title' => 'Acme Yoga', 'phone' => '020 1234']);
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->image('post.jpg', 800, 600)->size(60);
    }

    private function gbpPost(array $attributes = []): GbpPost
    {
        Storage::disk('local')->put('gbp-posts/a.jpg', str_repeat('x', 100));

        return GbpPost::create($attributes + ['client_id' => $this->client->id, 'location_id' => $this->location->id, 'post_type' => 'update',
            'caption' => 'Diwali yoga camp', 'image_path' => 'gbp-posts/a.jpg', 'scheduled_at' => now()->subMinute(), 'created_by' => $this->smm->id]);
    }

    public function test_smm_schedules_a_post_and_the_location_is_filled_in(): void
    {
        $this->actingAs($this->smm)->get(route('gbp-posts.create'))->assertOk()->assertSee('Acme Yoga');
        $this->actingAs($this->smm)->post(route('gbp-posts.store'), [
            'client_id' => $this->client->id, 'post_type' => 'update', 'caption' => 'Diwali yoga camp', 'image' => $this->image(),
            'cta_type' => 'LEARN_MORE', 'cta_url' => 'https://acme.test/camp', 'scheduled_at' => '2026-11-10 09:30',
        ])->assertRedirect(route('gbp-posts.index'))->assertSessionHas('status', 'Scheduled for 10 Nov, 09:30 AM.');

        $post = GbpPost::sole();
        $this->assertSame([$this->location->id, 'scheduled', $this->smm->id], [$post->location_id, $post->status, $post->created_by]);
        Storage::disk('local')->assertExists($post->image_path);
        $this->actingAs($this->smm)->get(route('gbp-posts.index'))->assertSee('Diwali yoga camp');
    }

    public function test_form_rules(): void
    {
        $other = Client::create(['name' => 'No GBP']);
        $this->actingAs($this->smm)->post(route('gbp-posts.store'), [
            'client_id' => $other->id, 'post_type' => 'offer', 'caption' => str_repeat('a', 1501), 'image' => UploadedFile::fake()->image('tiny.jpg', 100, 100),
            'cta_type' => 'BOOK',
        ])->assertSessionHasErrors(['client_id', 'caption', 'image', 'title', 'starts_at', 'ends_at', 'scheduled_at']);

        $this->actingAs($this->smm)->post(route('gbp-posts.store'), [
            'client_id' => $this->client->id, 'post_type' => 'update', 'caption' => 'Hi', 'image' => $this->image(), 'cta_type' => 'BOOK', 'now' => 1,
        ])->assertSessionHasErrors('cta_url');

        $this->location->update(['phone' => null]);
        $this->actingAs($this->smm)->post(route('gbp-posts.store'), [
            'client_id' => $this->client->id, 'post_type' => 'update', 'caption' => 'Hi', 'image' => $this->image(), 'cta_type' => 'CALL', 'now' => 1,
        ])->assertSessionHasErrors('cta_type');
        $this->assertSame(0, GbpPost::count());
    }

    public function test_post_now_is_published_with_a_signed_image_link(): void
    {
        Http::fake(['*/localPosts' => Http::response(['name' => 'accounts/11/locations/21/localPosts/99', 'searchUrl' => 'https://local.google.com/p/99', 'state' => 'LIVE'])]);
        $this->actingAs($this->smm)->post(route('gbp-posts.store'), [
            'client_id' => $this->client->id, 'post_type' => 'update', 'caption' => 'Open today', 'image' => $this->image(), 'cta_type' => 'CALL', 'now' => 1,
        ])->assertSessionHas('status', 'Queued. It goes to Google within a minute or two.');

        $this->assertSame(1, GbpPublisher::publishDue());

        $post = GbpPost::sole();
        $this->assertSame(['published', 'accounts/11/locations/21/localPosts/99', 'https://local.google.com/p/99'], [$post->status, $post->google_post_name, $post->search_url]);
        $sent = null;
        Http::assertSent(function (Request $r) use (&$sent) {
            $sent = $r->data();

            return $r->url() === 'https://mybusiness.googleapis.com/v4/accounts/11/locations/21/localPosts';
        });
        $this->assertSame(['STANDARD', 'Open today', ['actionType' => 'CALL']], [$sent['topicType'], $sent['summary'], $sent['callToAction']]);
        $imageUrl = $sent['media'][0]['sourceUrl'];
        $this->assertStringContainsString('/gbp/media/'.$post->id.'?expires=', $imageUrl);

        auth()->logout();
        $this->get($imageUrl)->assertOk();
        $this->get(route('gbp.media', $post))->assertForbidden();
        $this->assertSame(0, GbpPublisher::publishDue()); // not posted twice
    }

    public function test_offer_and_event_bodies(): void
    {
        $offer = $this->gbpPost(['post_type' => 'offer', 'title' => 'Diwali 20% off', 'starts_at' => '2026-11-01 10:00', 'ends_at' => '2026-11-15 20:30',
            'coupon' => 'DIWALI20', 'terms' => 'New members only', 'cta_url' => 'https://acme.test/offer']);
        $body = GbpPublisher::body($offer, 'https://img');
        $this->assertSame('OFFER', $body['topicType']);
        $this->assertSame(['title' => 'Diwali 20% off', 'schedule' => [
            'startDate' => ['year' => 2026, 'month' => 11, 'day' => 1], 'startTime' => ['hours' => 10, 'minutes' => 0],
            'endDate' => ['year' => 2026, 'month' => 11, 'day' => 15], 'endTime' => ['hours' => 20, 'minutes' => 30],
        ]], $body['event']);
        $this->assertSame(['couponCode' => 'DIWALI20', 'termsConditions' => 'New members only', 'redeemOnlineUrl' => 'https://acme.test/offer'], $body['offer']);
        $this->assertArrayNotHasKey('callToAction', $body);

        $event = $this->gbpPost(['post_type' => 'event', 'title' => 'Yoga camp', 'starts_at' => '2026-11-20 06:00', 'ends_at' => '2026-11-20 08:00',
            'cta_type' => 'SIGN_UP', 'cta_url' => 'https://acme.test/join']);
        $body = GbpPublisher::body($event, 'https://img');
        $this->assertSame(['EVENT', 'Yoga camp', ['actionType' => 'SIGN_UP', 'url' => 'https://acme.test/join']], [$body['topicType'], $body['event']['title'], $body['callToAction']]);
        $this->assertArrayNotHasKey('offer', $body);
    }

    public function test_failures_retry_with_uploaded_image_then_alert_the_team(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => Role::Admin]);
        $designer = User::factory()->create(['role' => Role::GraphicDesigner]);
        Http::fake([
            '*/media:startUpload' => Http::response(['resourceName' => 'ref-1']),
            '*/upload/v1/media/*' => Http::response([]),
            '*/locations/21/media' => Http::response(['name' => 'm1', 'googleUrl' => 'https://lh3.googleusercontent.com/p/abc']),
            '*/localPosts' => Http::response(['error' => ['message' => 'Media could not be fetched']], 400),
        ]);
        $post = $this->gbpPost();

        GbpPublisher::publishDue();
        $post->refresh();
        $this->assertSame(['scheduled', 1, 'Media could not be fetched'], [$post->status, $post->attempts, $post->error]);
        $this->assertTrue($post->retry_at->isFuture());
        $this->assertSame(0, GbpPublisher::publishDue()); // waits 5 minutes

        $this->travel(6)->minutes();
        GbpPublisher::publishDue();
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/localPosts') && $r['media'][0]['sourceUrl'] === 'https://lh3.googleusercontent.com/p/abc');
        Notification::assertNothingSent();

        $this->travel(6)->minutes();
        GbpPublisher::publishDue();
        $this->assertSame(['failed', 3], [$post->fresh()->status, $post->fresh()->attempts]);
        Notification::assertSentTo([$admin, $this->smm], WorkflowNotification::class, fn ($n) => $n->title === 'GBP post failed' && str_contains($n->message, 'Media could not be fetched'));
        Notification::assertNotSentTo($designer, WorkflowNotification::class);
        $this->actingAs($this->smm)->get(route('gbp-posts.index'))->assertSee('Fix &amp; retry', false);
    }

    public function test_saving_a_failed_post_retries_it_and_published_posts_are_locked(): void
    {
        $failed = $this->gbpPost(['status' => 'failed', 'attempts' => 3, 'error' => 'Bad image']);
        $this->actingAs($this->smm)->put(route('gbp-posts.update', $failed), [
            'client_id' => $this->client->id, 'post_type' => 'update', 'caption' => 'Fixed caption', 'now' => 1,
        ])->assertRedirect(route('gbp-posts.index'));
        $this->assertSame(['scheduled', 0, null, 'Fixed caption'], [$failed->fresh()->status, $failed->fresh()->attempts, $failed->fresh()->error, $failed->fresh()->caption]);

        $published = $this->gbpPost(['status' => 'published', 'published_at' => now()]);
        $this->actingAs($this->smm)->put(route('gbp-posts.update', $published), ['client_id' => $this->client->id, 'post_type' => 'update', 'caption' => 'x', 'now' => 1])->assertStatus(422);
        $this->actingAs($this->smm)->delete(route('gbp-posts.destroy', $published))->assertStatus(422);
        $this->actingAs($this->smm)->delete(route('gbp-posts.destroy', $failed))->assertRedirect();
        $this->assertNull($failed->fresh());
    }

    public function test_list_filters_and_access(): void
    {
        $other = Client::create(['name' => 'Beta']);
        $otherLocation = GbpLocation::create(['client_id' => $other->id, 'account_name' => 'accounts/11', 'location_name' => 'locations/22', 'title' => 'Beta']);
        $this->gbpPost(['caption' => 'Acme October', 'scheduled_at' => '2026-10-05 10:00', 'status' => 'published']);
        $this->gbpPost(['caption' => 'Acme November', 'scheduled_at' => '2026-11-05 10:00']);
        $this->gbpPost(['caption' => 'Beta November', 'client_id' => $other->id, 'location_id' => $otherLocation->id, 'scheduled_at' => '2026-11-06 10:00']);

        $this->actingAs($this->smm)->get(route('gbp-posts.index', ['month' => '2026-11']))->assertSee('Acme November')->assertSee('Beta November')->assertDontSee('Acme October');
        $this->actingAs($this->smm)->get(route('gbp-posts.index', ['status' => 'published']))->assertSee('Acme October')->assertDontSee('Acme November');
        $this->actingAs($this->smm)->get(route('gbp-posts.index', ['client' => $other->id]))->assertSee('Beta November')->assertDontSee('Acme November');

        $this->smm->brands()->attach($this->client->id); // works on Acme only
        $this->actingAs($this->smm->fresh())->get(route('gbp-posts.index'))->assertDontSee('Beta November');
        $this->actingAs($this->smm->fresh())->get(route('gbp-posts.edit', GbpPost::where('client_id', $other->id)->sole()))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => Role::GraphicDesigner]))->get(route('gbp-posts.index'))->assertForbidden();
    }
}
