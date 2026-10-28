<?php

namespace Tests\Feature;

use App\Enums\CalendarStatus;
use App\Enums\Role;
use App\Models\Asset;
use App\Models\Calendar;
use App\Models\ChatMessage;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
}
