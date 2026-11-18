<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Models\WhatsAppChat;
use App\Models\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WhatsAppTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.meta.app_secret' => 'app-secret', 'services.whatsapp' => [
            'token' => 'wa-token', 'waba_id' => 'WABA1', 'phone_id' => 'PHONE1', 'verify_token' => 'verify-me',
        ]]);
        $this->manager = User::factory()->create(['role' => Role::Manager]); // Ops Managers have the inbox by default
    }

    private function hook(array $changes): \Illuminate\Testing\TestResponse
    {
        $body = json_encode(['object' => 'whatsapp_business_account', 'entry' => [['id' => 'WABA1', 'changes' => $changes]]]);

        return $this->call('POST', '/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'app-secret'),
        ], $body);
    }

    private function incoming(string $id, string $text, ?int $at = null): array
    {
        return ['field' => 'messages', 'value' => ['messaging_product' => 'whatsapp',
            'metadata' => ['display_phone_number' => '91 90000 11111', 'phone_number_id' => 'PHONE1'],
            'contacts' => [['wa_id' => '919890840101', 'profile' => ['name' => 'Riya']]],
            'messages' => [['from' => '919890840101', 'id' => $id, 'timestamp' => (string) ($at ?? time()), 'type' => 'text', 'text' => ['body' => $text]]]]];
    }

    public function test_webhook_verify_needs_the_token(): void
    {
        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=12345')->assertOk()->assertSee('12345');
        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=12345')->assertForbidden();
    }

    public function test_unsigned_webhook_is_refused(): void
    {
        $this->postJson('/webhooks/whatsapp', ['entry' => []], ['X-Hub-Signature-256' => 'sha256=bad'])->assertForbidden();
    }

    public function test_incoming_message_opens_the_window_once_and_alerts_the_team(): void
    {
        $this->hook([$this->incoming('wamid.1', 'Hi, price?')])->assertOk();
        $this->hook([$this->incoming('wamid.1', 'Hi, price?')])->assertOk(); // Meta retries: saved once
        $this->hook([$this->incoming('wamid.2', 'For yoga classes')])->assertOk();

        $chat = WhatsAppChat::sole();
        $this->assertSame('Riya', $chat->name);
        $this->assertSame(2, $chat->unread);
        $this->assertNotNull($chat->windowEndsAt());
        $this->assertSame(2, $chat->messages()->count());
        $this->assertSame(1, $this->manager->notifications()->count()); // one alert per waiting chat

        $this->actingAs($this->manager)->get(route('whatsapp.show', $chat))->assertOk()->assertSee('Hi, price?')->assertSee('For yoga classes');
        $this->assertSame(0, $chat->fresh()->unread);
    }

    public function test_phone_echoes_history_and_contacts_come_in(): void
    {
        $this->hook([
            ['field' => 'smb_message_echoes', 'value' => ['metadata' => ['display_phone_number' => '919000011111'], 'message_echoes' => [
                ['from' => '919000011111', 'to' => '919890840101', 'id' => 'wamid.echo', 'timestamp' => (string) time(), 'type' => 'text', 'text' => ['body' => 'Sent from phone']]]]],
            ['field' => 'history', 'value' => ['metadata' => ['display_phone_number' => '91 90000 11111'], 'history' => [['threads' => [['id' => '917777766666', 'messages' => [
                ['from' => '917777766666', 'id' => 'wamid.h1', 'timestamp' => (string) now()->subHours(2)->timestamp, 'type' => 'text', 'text' => ['body' => 'Old question']],
                ['from' => '919000011111', 'id' => 'wamid.h2', 'timestamp' => (string) now()->subHour()->timestamp, 'type' => 'text', 'text' => ['body' => 'Old answer']],
            ]]]]]]],
            ['field' => 'smb_app_state_sync', 'value' => ['state_sync' => [['type' => 'contact', 'action' => 'add', 'contact' => ['full_name' => 'Amit Shah', 'phone_number' => '917777766666']]]]],
        ])->assertOk();

        $echo = WhatsAppMessage::where('wamid', 'wamid.echo')->sole();
        $this->assertSame(['out', 'phone'], [$echo->direction, $echo->source]);
        $old = WhatsAppChat::where('wa_id', '917777766666')->sole();
        $this->assertSame('Amit Shah', $old->name);
        $this->assertSame(['in', 'out'], $old->messages()->orderBy('sent_at')->pluck('direction')->all());
        $this->assertNull($old->windowEndsAt()); // history does not open the free window
        $this->assertSame(0, $this->manager->notifications()->count());
    }

    public function test_reply_inside_window_and_statuses_update_ticks(): void
    {
        $this->hook([$this->incoming('wamid.1', 'Hi')]);
        $chat = WhatsAppChat::sole();
        Http::fake(['*/PHONE1/messages' => Http::response(['messages' => [['id' => 'wamid.out1']]])]);

        $this->actingAs($this->manager)->post(route('whatsapp.send', $chat), ['text' => 'Hello Riya'])->assertRedirect(route('whatsapp.show', $chat));
        Http::assertSent(fn (Request $r) => $r['to'] === '919890840101' && $r['text']['body'] === 'Hello Riya' && $r->hasHeader('Authorization', 'Bearer wa-token'));
        $out = WhatsAppMessage::where('wamid', 'wamid.out1')->sole();
        $this->assertSame([$this->manager->id, 'sent'], [$out->user_id, $out->status]);

        $status = fn ($s) => ['field' => 'messages', 'value' => ['statuses' => [['id' => 'wamid.out1', 'status' => $s, 'timestamp' => (string) time()]]]];
        $this->hook([$status('read')]);
        $this->hook([$status('delivered')]); // late "delivered" does not undo "read"
        $this->assertSame('read', $out->fresh()->status);
    }

    public function test_closed_window_blocks_free_text_but_template_with_pdf_works(): void
    {
        Storage::fake('local');
        $chat = WhatsAppChat::create(['wa_id' => '919890840101', 'name' => 'Riya', 'last_inbound_at' => now()->subHours(30)]);
        Http::fake([
            '*/WABA1/message_templates*' => Http::response(['data' => [['name' => 'details_pdf', 'language' => 'en', 'category' => 'UTILITY', 'components' => [
                ['type' => 'HEADER', 'format' => 'DOCUMENT'], ['type' => 'BODY', 'text' => 'Hi {{1}}, here are the details you asked for.']]]]]),
            '*/PHONE1/media' => Http::response(['id' => 'MEDIA9']),
            '*/PHONE1/messages' => Http::response(['messages' => [['id' => 'wamid.t1']]]),
        ]);

        $this->actingAs($this->manager)->post(route('whatsapp.send', $chat), ['text' => 'Hello'])->assertSessionHasErrors('text');
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/messages'));

        $this->post(route('whatsapp.template', $chat), [
            'template' => 'details_pdf|en', 'params' => ['Riya'], 'file' => UploadedFile::fake()->create('rates.pdf', 50, 'application/pdf'),
        ])->assertRedirect(route('whatsapp.show', $chat));

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/PHONE1/messages') && $r['type'] === 'template'
            && $r['template']['name'] === 'details_pdf' && $r['template']['components'][0]['parameters'][0]['document']['id'] === 'MEDIA9'
            && $r['template']['components'][1]['parameters'][0]['text'] === 'Riya');
        $sent = WhatsAppMessage::where('wamid', 'wamid.t1')->sole();
        $this->assertSame(['document', 'Hi Riya, here are the details you asked for.', 'rates.pdf'], [$sent->type, $sent->body, $sent->file_name]);
        $this->get(route('whatsapp.show', $chat))->assertOk()->assertSee('details_pdf (en, utility)')->assertSee('rates.pdf')->assertSee('24-hour window closed');
        $this->get(route('whatsapp.media', [$sent, 'download' => 1]))->assertOk();
    }

    public function test_new_chat_by_number_needs_a_template(): void
    {
        Http::fake([
            '*/WABA1/message_templates*' => Http::response(['data' => [['name' => 'hello', 'language' => 'en', 'category' => 'MARKETING',
                'components' => [['type' => 'BODY', 'text' => 'Hello from Mega Techzy']]]]]),
            '*/PHONE1/messages' => Http::response(['messages' => [['id' => 'wamid.n1']]]),
        ]);
        $this->actingAs($this->manager)->post(route('whatsapp.new'), ['number' => '98908 40101', 'name' => 'Riya', 'template' => 'hello|en'])
            ->assertRedirect();
        $chat = WhatsAppChat::sole();
        $this->assertSame(['919890840101', 'Riya'], [$chat->wa_id, $chat->name]);
        $this->assertSame('Hello from Mega Techzy', $chat->messages()->sole()->body);
    }

    public function test_only_people_with_the_permission_see_the_inbox(): void
    {
        $chat = WhatsAppChat::create(['wa_id' => '919890840101']);
        $designer = User::factory()->create(['role' => Role::GraphicDesigner]);
        $this->actingAs($designer)->get(route('whatsapp.index'))->assertForbidden();
        $this->actingAs($designer)->get(route('whatsapp.show', $chat))->assertForbidden();

        $smm = User::factory()->create(['role' => Role::SocialMediaManager]);
        $this->actingAs($smm)->get(route('whatsapp.index'))->assertForbidden();
        \DB::table('user_permissions')->insert(['user_id' => $smm->id, 'permission' => 'whatsapp_chat']);
        Http::fake(['*' => Http::response(['data' => []])]);
        $this->actingAs($smm->fresh())->get(route('whatsapp.index'))->assertOk()->assertSee('+919890840101');
    }

    public function test_admin_connects_the_webhook_and_asks_for_history(): void
    {
        Http::fake([
            '*/WABA1/subscribed_apps' => Http::response(['success' => true]),
            '*/PHONE1/smb_app_data' => Http::response(['success' => true]),
            '*/WABA1/message_templates*' => Http::response(['data' => []]),
            '*/PHONE1?fields=*' => Http::response(['display_phone_number' => '+91 90000 11111', 'verified_name' => 'Mega Techzy', 'is_on_biz_app' => true]),
        ]);
        $admin = User::factory()->create(['role' => Role::Admin]);
        $this->actingAs($admin)->get(route('admin.whatsapp.settings'))->assertOk()->assertSee('Mega Techzy')->assertSee(route('whatsapp.webhook'));
        $this->post(route('admin.whatsapp.connect'), ['sync' => 1])->assertSessionHas('status');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/WABA1/subscribed_apps'));
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/smb_app_data') && $r['sync_type'] === 'history');
        $this->actingAs($this->manager)->get(route('admin.whatsapp.settings'))->assertForbidden();
    }
}
