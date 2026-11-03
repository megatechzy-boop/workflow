<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Client;
use App\Models\GbpLocation;
use App\Models\GbpReview;
use App\Models\GoogleAccount;
use App\Models\ReviewReplyLog;
use App\Models\ReviewTemplate;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use App\Support\GbpReviews;
use App\Support\ReviewTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Client $client;

    private GbpLocation $location;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => Role::Admin]);
        GoogleAccount::create(['access_token' => 'token', 'refresh_token' => 'r', 'expires_at' => now()->addHour()]);
        $this->client = Client::create(['name' => 'Acme', 'gbp_auto_reply' => true, 'gbp_language' => 'en']);
        $this->location = GbpLocation::create(['client_id' => $this->client->id, 'account_name' => 'accounts/11', 'location_name' => 'locations/21',
            'title' => 'Acme Yoga', 'city' => 'Pune', 'phone' => '020 1234']);
    }

    private function apiReview(string $id, string $stars, ?string $comment = 'Nice', ?string $name = 'Rahul Sharma', ?array $reply = null): array
    {
        return array_filter(['reviewId' => $id, 'name' => "accounts/11/locations/21/reviews/$id", 'reviewer' => ['displayName' => $name],
            'starRating' => $stars, 'comment' => $comment, 'createTime' => '2026-11-03T10:00:00Z', 'reviewReply' => $reply]);
    }

    private function fakeReviews(array ...$pages): void
    {
        $sequence = Http::sequence();
        foreach ($pages as $page) {
            $sequence->push($page);
        }
        Http::fake(['mybusiness.googleapis.com/v4/accounts/11/locations/21/reviews?*' => $sequence, '*/reply' => Http::response(['comment' => 'ok'])]);
    }

    private function review(int $rating, array $attributes = []): GbpReview
    {
        static $n = 0;
        $n++;

        return GbpReview::create($attributes + ['client_id' => $this->client->id, 'gbp_location_id' => $this->location->id, 'review_id' => "r$n",
            'review_name' => "accounts/11/locations/21/reviews/r$n", 'reviewer_name' => 'Rahul Sharma', 'rating' => $rating, 'comment' => 'Nice']);
    }

    public function test_default_templates_are_seeded_and_copied_to_new_clients(): void
    {
        $this->assertSame(50, ReviewTemplate::whereNull('client_id')->count());
        $this->assertSame(50, ReviewTemplate::where('client_id', $this->client->id)->count());
        $this->assertSame(5, ReviewTemplate::where('client_id', $this->client->id)->where('rating_group', 'neutral')->where('language', 'mr')->count());
    }

    public function test_first_sync_only_imports_then_new_reviews_follow_the_rules(): void
    {
        Notification::fake();
        $manager = User::factory()->create(['role' => Role::Manager]);
        $designer = User::factory()->create(['role' => Role::GraphicDesigner]);
        $this->fakeReviews(
            ['reviews' => [$this->apiReview('old1', 'ONE'), $this->apiReview('old2', 'FIVE')], 'nextPageToken' => 'p2'],
            ['reviews' => [$this->apiReview('old3', 'FIVE', reply: ['comment' => 'Thanks!', 'updateTime' => '2026-10-01T10:00:00Z'])]],
            ['reviews' => [$this->apiReview('new5', 'FIVE'), $this->apiReview('new3', 'THREE'), $this->apiReview('new2', 'TWO', 'Bad service'), $this->apiReview('old1', 'ONE')]],
        );

        $this->assertSame(3, GbpReviews::syncAll());
        $this->assertSame(['pending', 'pending', 'replied'], GbpReview::orderBy('id')->pluck('status')->all());
        Notification::assertNothingSent();

        $this->assertSame(3, GbpReviews::syncAll());
        $five = GbpReview::firstWhere('review_id', 'new5');
        $this->assertSame('scheduled', $five->status);
        $this->assertStringStartsWith('Hi Rahul,', $five->draft);
        $this->assertTrue($five->reply_due_at->between(now()->addMinutes(10), now()->addMinutes(30)));
        $three = GbpReview::firstWhere('review_id', 'new3');
        $this->assertSame(['draft', 'neutral'], [$three->status, ReviewTemplate::find($three->template_id)->rating_group]);
        $this->assertSame('pending', GbpReview::firstWhere('review_id', 'new2')->status);
        Notification::assertSentTo([$this->admin, $manager], WorkflowNotification::class, fn ($n) => $n->title === '2-star Google review');
        Notification::assertNotSentTo($designer, WorkflowNotification::class);
        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer token'));
    }

    public function test_due_auto_reply_is_sent_and_logged(): void
    {
        $this->fakeReviews();
        $review = $this->review(5, ['status' => 'scheduled', 'draft' => 'Hi Rahul, thanks!', 'reply_due_at' => now()->addMinutes(12)]);
        $later = $this->review(4, ['status' => 'scheduled', 'draft' => 'Later', 'reply_due_at' => now()->addMinutes(40)]);

        $this->travel(15)->minutes();
        $this->assertSame(1, GbpReviews::sendDue());

        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && str_ends_with($r->url(), 'accounts/11/locations/21/reviews/'.$review->review_id.'/reply')
            && $r['comment'] === 'Hi Rahul, thanks!');
        $this->assertSame('replied', $review->fresh()->status);
        $this->assertNull(ReviewReplyLog::sole()->approved_by);
        $this->assertSame('scheduled', $later->fresh()->status);
    }

    public function test_auto_reply_is_held_when_switched_off_and_fails_after_three_tries(): void
    {
        Http::fake(['*/reply' => Http::response(['error' => ['message' => 'Quota']], 429)]);
        $review = $this->review(5, ['status' => 'scheduled', 'draft' => 'Thanks', 'reply_due_at' => now()->subMinute()]);
        foreach ([1, 2, 3] as $try) {
            GbpReviews::sendDue();
            GbpReview::whereKey($review->id)->update(['reply_due_at' => now()->subMinute()]);
        }
        $this->assertSame(['pending', 3, 'Quota'], [$review->fresh()->status, $review->fresh()->attempts, $review->fresh()->error]);

        $this->client->update(['gbp_auto_reply' => false]);
        $held = $this->review(5, ['status' => 'scheduled', 'draft' => 'Thanks', 'reply_due_at' => now()->subMinute()]);
        GbpReviews::sendDue();
        $this->assertSame('pending', $held->fresh()->status);
    }

    public function test_greeting_language_and_healthcare(): void
    {
        $this->assertSame('Hi Rahul, thanks', ReviewTemplates::render('{greeting} thanks', 'en', ['name' => 'Rahul']));
        $this->assertSame('Hi there, thanks', ReviewTemplates::render('{greeting} thanks', 'en', ['name' => null]));
        $this->assertSame('Rahul जी,', ReviewTemplates::render('{greeting}', 'hi', ['name' => 'Rahul']));
        $this->assertSame('नमस्कार,', ReviewTemplates::render('{greeting}', 'mr', []));
        $this->assertSame('Namaskar,', ReviewTemplates::render('{greeting}', 'mr_en', []));
        $this->assertSame('Namaste, Acme · Pune · 1', ReviewTemplates::render('{greeting} {business} · {city} · {phone}', 'hinglish', ['business' => 'Acme', 'city' => 'Pune', 'phone' => '1']));

        $this->client->update(['gbp_auto_language' => true, 'gbp_language' => 'mr_en']);
        $this->assertSame('mr', ReviewTemplates::languageFor($this->client, 'खूप छान'));
        $this->assertSame('en', ReviewTemplates::languageFor($this->client, 'Very good'));
        $this->client->update(['gbp_language' => 'hinglish']);
        $this->assertSame('hi', ReviewTemplates::languageFor($this->client, 'बहुत अच्छा'));
        $this->client->update(['gbp_auto_language' => false]);
        $this->assertSame('hinglish', ReviewTemplates::languageFor($this->client, 'बहुत अच्छा'));

        $this->assertSame('Rahul', ReviewTemplates::firstName($this->client, 'rahul sharma'));
        $this->assertNull(ReviewTemplates::firstName($this->client, 'A Google User'));
        $this->client->update(['is_healthcare' => true]);
        $this->assertNull(ReviewTemplates::firstName($this->client, 'Rahul Sharma'));
        $picked = ReviewTemplates::pick($this->review(5)->load('client', 'location'), 'positive');
        $this->assertStringStartsWith('Namaste,', $picked['text']);
        $this->assertStringNotContainsString('Rahul', $picked['text']);
    }

    public function test_rotation_skips_the_last_three_and_templates_without_values(): void
    {
        ReviewTemplate::where('client_id', $this->client->id)->delete();
        $ids = collect(['A', 'B', 'C', 'D'])->map(fn ($body) => ReviewTemplate::create(['client_id' => $this->client->id, 'rating_group' => 'positive', 'language' => 'en', 'body' => $body])->id);
        ReviewTemplate::create(['client_id' => $this->client->id, 'rating_group' => 'positive', 'language' => 'en', 'body' => 'Call {phone}']);
        $this->location->update(['phone' => null]);

        $used = [];
        foreach (range(1, 8) as $i) {
            $review = $this->review(5)->load('client', 'location');
            $picked = ReviewTemplates::pick($review, 'positive');
            $this->assertNotContains($picked['template']->id, array_slice($used, -3));
            $this->assertNotSame('Call ', $picked['text']);
            $review->update(['template_id' => $picked['template']->id, 'templated_at' => now()]);
            $used[] = $picked['template']->id;
        }
        $this->assertEqualsCanonicalizing($ids->all(), array_unique($used));
    }

    public function test_team_replies_and_approves_drafts_from_the_dashboard(): void
    {
        $this->fakeReviews();
        $smm = User::factory()->create(['role' => Role::SocialMediaManager]);
        $template = ReviewTemplate::where('client_id', $this->client->id)->where('rating_group', 'neutral')->first();
        $draft = $this->review(3, ['status' => 'draft', 'draft' => 'Hi Rahul, thanks', 'template_id' => $template->id, 'comment' => 'Okay place']);
        $low = $this->review(1, ['comment' => 'Terrible']);
        $this->review(5, ['status' => 'replied', 'reply' => 'Thanks', 'replied_at' => now(), 'comment' => 'Superb']);

        $this->actingAs($smm)->get(route('reviews.index'))->assertOk()->assertSee('Okay place')->assertSee('Terrible')->assertDontSee('Superb')->assertSee('Approve &amp; post', false);
        $this->actingAs($smm)->get(route('reviews.index', ['stars' => 1]))->assertSee('Terrible')->assertDontSee('Okay place');
        $this->actingAs($smm)->get(route('reviews.index', ['status' => 'replied']))->assertSee('Superb')->assertDontSee('Terrible');
        $this->actingAs(User::factory()->create(['role' => Role::GraphicDesigner]))->get(route('reviews.index'))->assertForbidden();

        $this->actingAs($smm)->post(route('reviews.reply', $draft), ['reply' => 'Hi Rahul, thanks a lot'])->assertSessionHas('status', 'Reply posted on Google.');
        $this->actingAs($smm)->post(route('reviews.reply', $low), ['reply' => 'Sorry, please call us'])->assertSessionHas('status');

        $logs = ReviewReplyLog::orderBy('id')->get();
        $this->assertSame([$template->id, null], $logs->pluck('template_id')->all());
        $this->assertSame([$smm->id, $smm->id], $logs->pluck('approved_by')->all());
        $this->assertSame('Hi Rahul, thanks a lot', $draft->fresh()->reply);
        $this->assertSame('replied', $low->fresh()->status);
    }

    public function test_admin_manages_templates_with_duplicate(): void
    {
        $this->actingAs($this->admin)->get(route('admin.review-templates.index'))->assertOk()->assertSee('5 active');
        $this->actingAs($this->admin)->post(route('admin.review-templates.store'), [
            'client_id' => $this->client->id, 'rating_group' => 'positive', 'language' => 'hinglish', 'body' => '{greeting} shukriya!', 'is_active' => 1,
        ])->assertRedirect(route('admin.review-templates.index', ['client' => $this->client->id]));
        $template = ReviewTemplate::latest('id')->first();
        $this->actingAs($this->admin)->get(route('admin.review-templates.edit', $template))->assertOk()->assertSee('Acme Yoga');

        $this->actingAs($this->admin)->post(route('admin.review-templates.duplicate', $template))->assertRedirect();
        $copy = ReviewTemplate::latest('id')->first();
        $this->assertSame([$template->body, $this->client->id], [$copy->body, $copy->client_id]);
        $this->actingAs($this->admin)->put(route('admin.review-templates.update', $copy), [
            'client_id' => $this->client->id, 'rating_group' => 'positive', 'language' => 'hinglish', 'body' => '{greeting} dhanyavaad!',
        ]);
        $this->assertSame(['{greeting} dhanyavaad!', false], [$copy->fresh()->body, $copy->fresh()->is_active]);
        $this->actingAs(User::factory()->create(['role' => Role::Manager]))->get(route('admin.review-templates.index'))->assertForbidden();
    }

    public function test_client_form_saves_review_settings(): void
    {
        $this->actingAs($this->admin)->put(route('clients.update', $this->client), [
            'name' => 'Acme', 'is_active' => 1, 'gbp_auto_reply' => 1, 'gbp_language' => 'mr', 'gbp_auto_language' => 1, 'is_healthcare' => 1,
        ])->assertRedirect(route('clients.index'));
        $client = $this->client->fresh();
        $this->assertSame([true, 'mr', true, true], [$client->gbp_auto_reply, $client->gbp_language, $client->gbp_auto_language, $client->is_healthcare]);
        $this->actingAs($this->admin)->get(route('clients.edit', $client))->assertOk()->assertSee('Google reviews');
    }
}
