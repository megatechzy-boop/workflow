<?php

namespace Tests\Feature;

use App\Enums\CalendarStatus;
use App\Enums\ContentStatus;
use App\Enums\Role;
use App\Models\Asset;
use App\Models\Calendar;
use App\Models\ChatMessage;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use App\Notifications\ChatPush;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssetChatTest extends TestCase
{
    use RefreshDatabase;

    private Client $acme;

    private Client $other;

    private User $customer;

    private User $smm;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->acme = Client::create(['name' => 'Acme']);
        $this->other = Client::create(['name' => 'Other']);
        $this->customer = User::factory()->create(['role' => Role::Client, 'client_id' => $this->acme->id]);
        $this->smm = User::factory()->create(['role' => Role::SocialMediaManager, 'name' => 'Sana SMM']);
    }

    public function test_customer_uploads_to_library_and_calendar_row(): void
    {
        $calendar = Calendar::create(['client_id' => $this->acme->id, 'month' => '2026-11-01', 'created_by' => $this->smm->id, 'status' => CalendarStatus::ApproverApproved]);
        $item = $calendar->items()->create(['post_date' => '2026-11-05', 'post_types' => ['instagram_post']]);

        $this->actingAs($this->customer)->post('/assets', [
            'client_id' => $this->acme->id,
            'files' => [UploadedFile::fake()->image('shoot.jpg'), UploadedFile::fake()->create('demo.mp4', 2000, 'video/mp4')],
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->customer)->post('/assets', [
            'client_id' => $this->acme->id, 'calendar_item_id' => $item->id, 'files' => [UploadedFile::fake()->image('row.jpg')],
        ])->assertSessionHasNoErrors();

        $this->assertSame(3, Asset::count());
        $this->assertSame(1, $item->assets()->count());
        $this->actingAs($this->smm)->get("/calendars/{$calendar->id}")->assertSee('row.jpg');
        $this->actingAs($this->smm)->get('/assets?client='.$this->acme->id)->assertSee('shoot.jpg');
        $this->actingAs($this->smm)->get('/assets/'.Asset::first()->id.'/download')->assertOk();
    }

    public function test_customer_cannot_touch_other_brand_assets(): void
    {
        $this->actingAs($this->customer)->post('/assets', [
            'client_id' => $this->other->id, 'files' => [UploadedFile::fake()->image('x.jpg')],
        ])->assertForbidden();

        $otherAsset = Asset::create(['client_id' => $this->other->id, 'uploaded_by' => $this->smm->id, 'path' => 'assets/x.jpg', 'original_name' => 'secret.jpg', 'mime' => 'image/jpeg', 'size' => 10]);
        $this->actingAs($this->customer)->get("/assets/{$otherAsset->id}/download")->assertForbidden();
        $this->actingAs($this->customer)->get('/assets?client='.$this->other->id)->assertDontSee('secret.jpg');
        // cannot delete someone else's file
        $this->actingAs($this->customer)->delete("/assets/{$otherAsset->id}")->assertForbidden();
    }

    public function test_only_media_files_allowed(): void
    {
        $this->actingAs($this->customer)->post('/assets', [
            'client_id' => $this->acme->id, 'files' => [UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')],
        ])->assertSessionHasErrors('files.0');
    }

    public function test_videos_up_to_one_gb(): void
    {
        $this->actingAs($this->customer)->post('/assets', [
            'client_id' => $this->acme->id, 'files' => [UploadedFile::fake()->create('shoot.mp4', 200 * 1024, 'video/mp4')],
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->customer)->post('/assets', [
            'client_id' => $this->acme->id, 'files' => [UploadedFile::fake()->create('huge.mp4', 1025 * 1024, 'video/mp4')],
        ])->assertSessionHasErrors('files.0');
    }

    public function test_chat_rooms_access(): void
    {
        $this->assertSame(['client:'.$this->acme->id], array_keys(ChatMessage::roomsFor($this->customer)));
        $this->assertArrayHasKey('internal:'.$this->acme->id, ChatMessage::roomsFor($this->smm));

        // Customer: own client group yes; internal / other brand / team no
        $this->actingAs($this->customer)->postJson('/chat/client:'.$this->acme->id.'/messages', ['body' => 'Hello team'])->assertCreated();
        $this->actingAs($this->customer)->postJson('/chat/internal:'.$this->acme->id.'/messages', ['body' => 'x'])->assertForbidden();
        $this->actingAs($this->customer)->postJson('/chat/client:'.$this->other->id.'/messages', ['body' => 'x'])->assertForbidden();
        $this->actingAs($this->customer)->getJson('/chat/team/messages')->assertForbidden();

        // internal team messages are hidden from the customer
        $this->actingAs($this->smm)->postJson('/chat/internal:'.$this->acme->id.'/messages', ['body' => 'Internal note'])->assertCreated();
        $this->actingAs($this->customer)->get('/chat')->assertOk()->assertSee('Hello team')->assertDontSee('Internal note');
    }

    public function test_unread_count_mention_and_file(): void
    {
        $room = 'client:'.$this->acme->id;

        $this->actingAs($this->customer)->postJson("/chat/$room/messages", ['body' => 'See photo @SanaSMM', 'file' => UploadedFile::fake()->image('pic.jpg')])
            ->assertCreated()->assertJsonPath('file.name', 'pic.jpg');

        $this->assertSame(1, ChatMessage::unreadCounts($this->smm)[$room] ?? 0);
        $this->assertSame('You were mentioned in chat', $this->smm->notifications()->first()->data['title']);

        // opening the room marks it read
        $this->actingAs($this->smm)->get('/chat?room='.$room)->assertOk();
        $this->assertSame(0, ChatMessage::unreadCounts($this->smm)[$room] ?? 0);

        // attachments only for room members
        $msg = ChatMessage::first();
        $this->actingAs($this->smm)->get("/chat/attachment/{$msg->id}")->assertOk();
        $stranger = User::factory()->create(['role' => Role::Client, 'client_id' => $this->other->id]);
        $this->actingAs($stranger)->get("/chat/attachment/{$msg->id}")->assertForbidden();
    }

    public function test_push_subscription_and_chat_push(): void
    {
        $this->actingAs($this->smm)->postJson('/push-subscriptions', ['endpoint' => 'http://evil.test/x', 'keys' => ['p256dh' => 'k', 'auth' => 'a']])
            ->assertUnprocessable();
        $this->actingAs($this->smm)->postJson('/push-subscriptions', ['endpoint' => 'https://push.example.com/abc', 'keys' => ['p256dh' => 'k', 'auth' => 'a']])
            ->assertNoContent();
        $this->assertSame(1, $this->smm->pushSubscriptions()->count());

        Notification::fake();
        $room = 'client:'.$this->acme->id;
        $this->actingAs($this->customer)->postJson("/chat/$room/messages", ['body' => 'Hi'])->assertCreated();
        Notification::assertSentTo($this->smm, ChatPush::class, fn ($n) => $n->message === 'Hi');
        Notification::assertNotSentTo($this->customer, ChatPush::class);

        $this->actingAs($this->smm)->deleteJson('/push-subscriptions', ['endpoint' => 'https://push.example.com/abc'])->assertNoContent();
        $this->assertSame(0, $this->smm->pushSubscriptions()->count());
    }

    public function test_reply_delete_seen_and_search(): void
    {
        $room = 'client:'.$this->acme->id;
        $first = $this->actingAs($this->customer)->postJson("/chat/$room/messages", ['body' => 'Logo is ready'])->json('id');
        $internal = $this->actingAs($this->smm)->postJson('/chat/internal:'.$this->acme->id.'/messages', ['body' => 'secret'])->json('id');

        // reply works inside the room; a reply to another room's message is dropped
        $this->actingAs($this->smm)->postJson("/chat/$room/messages", ['body' => 'Thanks', 'reply_to_id' => $first])
            ->assertJsonPath('reply.text', 'Logo is ready');
        $this->actingAs($this->customer)->postJson("/chat/$room/messages", ['body' => 'x', 'reply_to_id' => $internal])
            ->assertJsonPath('reply', null);

        // seen by: the SMM opened the room after the first message
        $this->actingAs($this->customer)->getJson("/chat/message/$first/seen")->assertExactJson([$this->smm->name]);
        $this->actingAs($this->smm)->getJson("/chat/message/$first/seen")->assertForbidden();

        // search
        $this->actingAs($this->smm)->get("/chat?room=$room&q=Logo")->assertOk()->assertSee('1 result(s)', false);

        // delete: only own, only within the time limit; the poll tells other screens
        $this->actingAs($this->smm)->deleteJson("/chat/message/$first")->assertForbidden();
        $this->actingAs($this->customer)->deleteJson("/chat/message/$first")->assertOk()->assertJsonPath('deleted', true);
        $this->assertNull(ChatMessage::find($first)->body);
        $this->assertContains($first, $this->actingAs($this->smm)->getJson("/chat/$room/messages?after=0")->json('deleted'));

        $old = ChatMessage::create(['room' => $room, 'user_id' => $this->customer->id, 'body' => 'old']);
        $old->forceFill(['created_at' => now()->subMinutes(ChatMessage::DELETE_MINUTES + 1)])->save();
        $this->actingAs($this->customer)->deleteJson("/chat/message/{$old->id}")->assertStatus(422);
    }

    public function test_discuss_content_in_chat(): void
    {
        $calendar = Calendar::create(['client_id' => $this->acme->id, 'month' => '2026-11-01', 'created_by' => $this->smm->id, 'status' => CalendarStatus::ClientApproved]);
        $item = $calendar->items()->create(['post_date' => '2026-11-05', 'post_types' => ['instagram_reel']]);
        $task = $item->task()->create(['assigned_to' => $this->smm->id, 'assigned_by' => $this->smm->id, 'deadline' => '2026-11-03']);
        $content = $task->contents()->create(['post_type' => 'reel', 'approver_id' => $this->smm->id, 'status' => ContentStatus::ClientReview, 'current_version' => 1]);
        $room = 'client:'.$this->acme->id;

        $this->actingAs($this->customer)->get("/chat?room=$room&content={$content->id}")->assertOk()->assertSee('05 Nov · Reel · Instagram', false);
        $this->actingAs($this->customer)->postJson("/chat/$room/messages", ['body' => 'Change music?', 'content_id' => $content->id])
            ->assertCreated()->assertJsonPath('content.label', '05 Nov · Reel · Instagram')
            ->assertJsonPath('content.url', route('contents.show', $content));

        // SMM picks a post inside the chat; customers cannot use the picker
        $this->actingAs($this->smm)->getJson("/chat/$room/posts")->assertOk()->assertJsonPath('0.label', '05 Nov · Reel · Instagram');
        $this->actingAs($this->smm)->getJson('/chat/client:'.$this->other->id.'/posts')->assertOk()->assertJsonCount(0);
        $this->actingAs($this->smm)->getJson('/chat/team/posts')->assertForbidden();
        $this->actingAs($this->customer)->getJson("/chat/$room/posts")->assertForbidden();

        // another brand's room, or a post the customer cannot see yet => refused
        $this->actingAs($this->smm)->postJson('/chat/client:'.$this->other->id.'/messages', ['body' => 'x', 'content_id' => $content->id])->assertForbidden();
        $content->update(['status' => ContentStatus::Submitted]);
        $this->actingAs($this->customer)->postJson("/chat/$room/messages", ['body' => 'x', 'content_id' => $content->id])->assertForbidden();
    }

    public function test_pin_mute_quiet_hours_and_media(): void
    {
        $room = 'client:'.$this->acme->id;
        $id = $this->actingAs($this->customer)->postJson("/chat/$room/messages", ['body' => 'Brand colours: teal'])->json('id');
        $photo = $this->actingAs($this->customer)->postJson("/chat/$room/messages", ['file' => UploadedFile::fake()->image('logo.png')])->json('id');

        // pin: team only, one per group, toggles
        $this->actingAs($this->customer)->postJson("/chat/message/$id/pin")->assertForbidden();
        $this->actingAs($this->smm)->postJson("/chat/message/$photo/pin")->assertJsonPath('pinned.id', $photo);
        $this->actingAs($this->smm)->postJson("/chat/message/$id/pin")->assertJsonPath('pinned.id', $id);
        $this->assertSame(1, ChatMessage::whereNotNull('pinned_at')->count());
        $this->actingAs($this->smm)->getJson("/chat/$room/messages?after=0")->assertJsonPath('pinned.id', $id);
        $this->actingAs($this->smm)->postJson("/chat/message/$id/pin")->assertJsonPath('pinned', null);

        // media
        $this->actingAs($this->smm)->getJson("/chat/$room/media")->assertJsonCount(1)->assertJsonPath('0.name', 'logo.png');

        Notification::fake();
        $this->actingAs($this->smm)->postJson("/chat/$room/mute")->assertJsonPath('muted', true);
        $this->travelTo(now()->setTime(23, 0));
        $this->actingAs($this->smm)->postJson("/chat/$room/messages", ['body' => 'night'])->assertCreated();
        Notification::assertNotSentTo($this->customer, ChatPush::class); // quiet hours
        $this->actingAs($this->customer)->postJson("/chat/$room/messages", ['body' => 'hi'])->assertCreated();
        Notification::assertNotSentTo($this->smm, ChatPush::class); // muted

        $this->travelTo(now()->setTime(11, 0));
        $this->actingAs($this->smm)->postJson("/chat/$room/mute")->assertJsonPath('muted', false);
        $this->actingAs($this->customer)->postJson("/chat/$room/messages", ['body' => 'day'])->assertCreated();
        Notification::assertSentTo($this->smm, ChatPush::class);
    }

    public function test_voice_note(): void
    {
        $room = 'client:'.$this->acme->id;
        $this->actingAs($this->customer)->postJson("/chat/$room/messages", [
            'file' => UploadedFile::fake()->create('Voice note 10:00.m4a', 20, 'audio/mp4'),
        ])->assertCreated()->assertJsonPath('file.audio', true);
    }
}
