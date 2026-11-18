<?php

namespace App\Support;

use App\Enums\Permission;
use App\Models\User;
use App\Models\WhatsAppChat;
use App\Models\WhatsAppMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * WhatsApp Cloud API on the company number, in coexistence with the WhatsApp Business app:
 * the phone and WhatsApp Web keep working, their messages come in as "echoes".
 * Token / IDs only in the server .env (system user token with whatsapp_business_messaging + management).
 */
class WhatsApp
{
    public static function ready(): bool
    {
        return (bool) (config('services.whatsapp.token') && config('services.whatsapp.phone_id') && config('services.whatsapp.waba_id'));
    }

    // ---- Sending -------------------------------------------------------------------------------

    public static function sendText(WhatsAppChat $chat, string $text, User $user): WhatsAppMessage
    {
        self::needsWindow($chat);
        $id = self::send($chat->wa_id, ['type' => 'text', 'text' => ['body' => $text, 'preview_url' => true]]);

        return self::saveOut($chat, $user, $id, ['type' => 'text', 'body' => $text]);
    }

    /** PDF / photo from the inbox (inside the 24-hour window) */
    public static function sendFile(WhatsAppChat $chat, UploadedFile $file, ?string $caption, User $user): WhatsAppMessage
    {
        self::needsWindow($chat);
        $type = str_starts_with((string) $file->getMimeType(), 'image/') ? 'image' : 'document';
        $path = $file->store('whatsapp/out', 'local');
        $mediaId = self::upload($path, $file->getMimeType());
        $payload = ['id' => $mediaId] + ($caption ? ['caption' => $caption] : [])
            + ($type === 'document' ? ['filename' => $file->getClientOriginalName()] : []);
        $id = self::send($chat->wa_id, ['type' => $type, $type => $payload]);

        return self::saveOut($chat, $user, $id, ['type' => $type, 'body' => $caption, 'media_id' => $mediaId,
            'media_path' => $path, 'file_name' => $file->getClientOriginalName(), 'mime' => $file->getMimeType()]);
    }

    /**
     * Approved template: works any time (first message, or after 24 hours). Meta charges per template.
     *
     * @param  array<int, string>  $params  body {{1}}, {{2}}…
     */
    public static function sendTemplate(WhatsAppChat $chat, array $template, array $params, ?UploadedFile $headerFile, User $user): WhatsAppMessage
    {
        $components = [];
        $saved = [];
        $header = $template['header'] ?? null;
        if (in_array($header, ['DOCUMENT', 'IMAGE'], true)) {
            throw_unless($headerFile, RuntimeException::class, 'This template needs a '.($header === 'DOCUMENT' ? 'PDF' : 'photo').'.');
            $kind = strtolower($header);
            $path = $headerFile->store('whatsapp/out', 'local');
            $mediaId = self::upload($path, $headerFile->getMimeType());
            $components[] = ['type' => 'header', 'parameters' => [['type' => $kind, $kind => ['id' => $mediaId]
                + ($kind === 'document' ? ['filename' => $headerFile->getClientOriginalName()] : [])]]];
            $saved = ['media_id' => $mediaId, 'media_path' => $path, 'file_name' => $headerFile->getClientOriginalName(), 'mime' => $headerFile->getMimeType()];
        }
        $params = array_values(array_map('strval', $params));
        if ($params) {
            $components[] = ['type' => 'body', 'parameters' => array_map(fn ($p) => ['type' => 'text', 'text' => $p], $params)];
        }
        $id = self::send($chat->wa_id, ['type' => 'template', 'template' => ['name' => $template['name'],
            'language' => ['code' => $template['language']]] + ($components ? ['components' => $components] : [])]);

        // the text as the customer sees it ({{1}} filled in)
        $body = preg_replace_callback('/\{\{(\d+)\}\}/', fn ($m) => $params[(int) $m[1] - 1] ?? $m[0], (string) ($template['body'] ?? ''));

        return self::saveOut($chat, $user, $id, ['type' => $saved ? ($header === 'DOCUMENT' ? 'document' : 'image') : 'template', 'body' => $body] + $saved);
    }

    /** Approved templates of the WhatsApp account (cached 10 minutes) */
    public static function templates(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget('whatsapp-templates');
        }

        return Cache::remember('whatsapp-templates', 600, function () {
            $data = self::graph()->get(self::url('/'.config('services.whatsapp.waba_id').'/message_templates?status=APPROVED&limit=100&fields=name,language,category,components'))
                ->throw()->json('data') ?? [];

            return collect($data)->map(function ($t) {
                $components = collect($t['components'] ?? []);
                $body = (string) ($components->firstWhere('type', 'BODY')['text'] ?? '');
                preg_match_all('/\{\{(\d+)\}\}/', $body, $m);

                return ['key' => $t['name'].'|'.$t['language'], 'name' => $t['name'], 'language' => $t['language'], 'category' => $t['category'] ?? '',
                    'body' => $body, 'params' => $m[1] ? max(array_map('intval', $m[1])) : 0,
                    'header' => $components->firstWhere('type', 'HEADER')['format'] ?? null];
            })->sortBy('name')->values()->all();
        });
    }

    private static function needsWindow(WhatsAppChat $chat): void
    {
        throw_unless($chat->windowEndsAt(), RuntimeException::class,
            'The 24-hour window is closed: send a template, or reply from the WhatsApp app / Web.');
    }

    private static function send(string $to, array $message): string
    {
        $response = self::graph()->post(self::url('/'.config('services.whatsapp.phone_id').'/messages'),
            ['messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => $to] + $message);
        self::fail($response);

        return (string) $response->json('messages.0.id');
    }

    private static function upload(string $path, ?string $mime): string
    {
        $disk = Storage::disk('local');
        $stream = $disk->readStream($path);
        try {
            $response = self::graph()->attach('file', $stream, basename($path), ['Content-Type' => $mime ?: 'application/octet-stream'])
                ->post(self::url('/'.config('services.whatsapp.phone_id').'/media'), ['messaging_product' => 'whatsapp', 'type' => $mime]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        self::fail($response);

        return (string) $response->json('id');
    }

    private static function saveOut(WhatsAppChat $chat, User $user, string $wamid, array $data): WhatsAppMessage
    {
        $message = $chat->messages()->create($data + ['wamid' => $wamid ?: null, 'direction' => 'out', 'source' => 'app',
            'user_id' => $user->id, 'status' => 'sent', 'sent_at' => now()]);
        $chat->update(['last_message_at' => now(), 'unread' => 0]);

        return $message;
    }

    // ---- Setup (Admin > WhatsApp) -----------------------------------------------------------------

    /** Number + name as Meta shows them */
    public static function phoneInfo(): array
    {
        return self::graph()->get(self::url('/'.config('services.whatsapp.phone_id').'?fields=display_phone_number,verified_name,quality_rating,is_on_biz_app,platform_type'))
            ->throw()->json();
    }

    /** Our Meta app gets this account's webhooks */
    public static function subscribe(): void
    {
        self::fail(self::graph()->post(self::url('/'.config('services.whatsapp.waba_id').'/subscribed_apps')));
    }

    /** Ask Meta for the phone's contacts and the last 6 months of chats (only within 24 hours of linking the number) */
    public static function requestSync(): array
    {
        $done = [];
        foreach (['smb_app_state_sync', 'history'] as $type) {
            $response = self::graph()->post(self::url('/'.config('services.whatsapp.phone_id').'/smb_app_data'),
                ['messaging_product' => 'whatsapp', 'sync_type' => $type]);
            $done[$type] = $response->successful() ? 'requested' : ($response->json('error.message') ?? 'failed');
        }

        return $done;
    }

    // ---- Media -------------------------------------------------------------------------------------

    /** Local copy of a message's photo / PDF (incoming files are downloaded from Meta the first time) */
    public static function mediaPath(WhatsAppMessage $message): string
    {
        if ($message->media_path && Storage::disk('local')->exists($message->media_path)) {
            return $message->media_path;
        }
        throw_unless($message->media_id, RuntimeException::class, 'No file.');
        $info = self::graph()->get(self::url('/'.$message->media_id))->throw()->json();
        $bytes = self::graph()->get($info['url'])->throw()->body();
        $ext = match (true) {
            (bool) $message->file_name && str_contains($message->file_name, '.') => Str::afterLast($message->file_name, '.'),
            default => explode('/', (string) ($info['mime_type'] ?? 'application/octet-stream'))[1] ?? 'bin',
        };
        $path = 'whatsapp/in/'.$message->id.'.'.preg_replace('/[^a-z0-9]/i', '', explode(';', $ext)[0]);
        Storage::disk('local')->put($path, $bytes);
        $message->update(['media_path' => $path, 'mime' => $message->mime ?? ($info['mime_type'] ?? null)]);

        return $path;
    }

    // ---- Webhook -----------------------------------------------------------------------------------

    public static function validSignature(string $body, ?string $header): bool
    {
        $secret = (string) config('services.meta.app_secret');

        return $secret !== '' && $header !== null && hash_equals('sha256='.hash_hmac('sha256', $body, $secret), $header);
    }

    /** Meta webhook payload (messages, statuses, phone echoes, history, contacts) */
    public static function handle(array $payload): void
    {
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                $business = preg_replace('/\D+/', '', (string) ($value['metadata']['display_phone_number'] ?? ''));
                match ($change['field'] ?? '') {
                    'messages' => self::onMessages($value),
                    'smb_message_echoes' => array_map(fn ($m) => self::store($m, 'out', 'phone', $m['to'] ?? ''), $value['message_echoes'] ?? []),
                    'history' => self::onHistory($value, $business),
                    'smb_app_state_sync' => self::onContacts($value),
                    default => null,
                };
            }
        }
    }

    private static function onMessages(array $value): void
    {
        $names = collect($value['contacts'] ?? [])->mapWithKeys(fn ($c) => [$c['wa_id'] ?? '' => $c['profile']['name'] ?? null]);
        foreach ($value['messages'] ?? [] as $m) {
            $chat = self::chat($m['from'] ?? '', $names[$m['from'] ?? ''] ?? null);
            $hadUnread = $chat->unread > 0;
            if (self::store($m, 'in', null, $chat->wa_id)) {
                $chat->increment('unread');
                if (! $hadUnread) { // one alert per waiting chat, not one per message
                    Notify::send(self::team(), 'WhatsApp: '.$chat->title(), Str::limit(self::summary($m), 80), route('whatsapp.show', $chat));
                }
            }
        }
        foreach ($value['statuses'] ?? [] as $s) {
            $message = WhatsAppMessage::where('wamid', $s['id'] ?? '')->first();
            if (! $message) {
                continue;
            }
            $rank = ['sent' => 1, 'delivered' => 2, 'read' => 3, 'failed' => 4];
            $new = $s['status'] ?? '';
            if (($rank[$new] ?? 0) > ($rank[$message->status] ?? 0)) { // statuses can arrive out of order
                $message->update(['status' => $new, 'error' => $new === 'failed'
                    ? Str::limit(($s['errors'][0]['title'] ?? 'Failed').': '.($s['errors'][0]['error_data']['details'] ?? $s['errors'][0]['message'] ?? ''), 490) : null]);
            }
        }
    }

    private static function onHistory(array $value, string $business): void
    {
        foreach ($value['history'] ?? [] as $chunk) {
            foreach ($chunk['threads'] ?? [] as $thread) {
                foreach ($thread['messages'] ?? [] as $m) {
                    $mine = $business !== '' && preg_replace('/\D+/', '', (string) ($m['from'] ?? '')) === $business;
                    self::store($m, $mine ? 'out' : 'in', $mine ? 'phone' : null, (string) ($thread['id'] ?? $m['to'] ?? $m['from'] ?? ''), history: true);
                }
            }
        }
    }

    private static function onContacts(array $value): void
    {
        foreach ($value['state_sync'] ?? [] as $item) {
            $contact = $item['contact'] ?? [];
            if (($item['type'] ?? '') === 'contact' && ($item['action'] ?? 'add') !== 'remove' && ! empty($contact['phone_number'])) {
                $chat = self::chat($contact['phone_number']);
                $chat->update(['name' => $contact['full_name'] ?? $contact['first_name'] ?? $chat->name]); // phone's saved name wins
            }
        }
    }

    private static function chat(string $number, ?string $name = null): WhatsAppChat
    {
        $chat = WhatsAppChat::firstOrCreate(['wa_id' => WhatsAppChat::normalise($number)], ['name' => $name]);
        if ($name && ! $chat->name) {
            $chat->update(['name' => $name]);
        }

        return $chat;
    }

    /** Saves one message once (Meta may send the same webhook again); null if it was already there */
    private static function store(array $m, string $direction, ?string $source, string $customer, bool $history = false): ?WhatsAppMessage
    {
        if (! $customer || empty($m['id']) || WhatsAppMessage::where('wamid', $m['id'])->exists()) {
            return null;
        }
        $chat = self::chat($customer);
        $type = $m['type'] ?? 'other';
        $media = $m[$type] ?? [];
        $at = Carbon::createFromTimestamp((int) ($m['timestamp'] ?? time()));
        $message = $chat->messages()->create([
            'wamid' => $m['id'], 'direction' => $direction, 'source' => $direction === 'out' ? ($source ?? 'phone') : 'app',
            'type' => in_array($type, ['text', 'image', 'document', 'audio', 'video', 'sticker', 'template'], true) ? $type : 'other',
            'body' => self::summary($m, false), 'media_id' => is_array($media) ? ($media['id'] ?? null) : null,
            'file_name' => $media['filename'] ?? null, 'mime' => $media['mime_type'] ?? null,
            'status' => $direction === 'out' ? 'sent' : null, 'sent_at' => $at,
        ]);
        $chat->forceFill([
            'last_message_at' => max($chat->last_message_at ?? $at, $at),
            // history is old: it must not open the free 24-hour window
            'last_inbound_at' => $direction === 'in' && ! $history ? max($chat->last_inbound_at ?? $at, $at) : $chat->last_inbound_at,
        ])->save();

        return $message;
    }

    /** Text of a message; for files the caption or a label */
    private static function summary(array $m, bool $label = true): ?string
    {
        $type = $m['type'] ?? 'other';

        return match ($type) {
            'text' => $m['text']['body'] ?? '',
            'button' => $m['button']['text'] ?? '',
            'interactive' => $m['interactive']['button_reply']['title'] ?? $m['interactive']['list_reply']['title'] ?? 'Reply',
            'location' => 'Location: '.($m['location']['name'] ?? '').' https://maps.google.com/?q='.($m['location']['latitude'] ?? '').','.($m['location']['longitude'] ?? ''),
            'contacts' => 'Contact: '.($m['contacts'][0]['name']['formatted_name'] ?? ''),
            'reaction' => 'Reacted '.($m['reaction']['emoji'] ?? ''),
            'image', 'document', 'video', 'audio', 'sticker' => ($m[$type]['caption'] ?? null) ?: ($label ? ucfirst($type) : null),
            default => $label ? 'Message ('.$type.')' : 'Message ('.$type.') - open WhatsApp to see it',
        };
    }

    /** People with the WhatsApp inbox (Admins always) */
    public static function team(): \Illuminate\Support\Collection
    {
        return User::where('is_active', true)->where('role', '!=', \App\Enums\Role::Client)->get()
            ->filter(fn (User $u) => $u->hasPermission(Permission::WhatsAppChat));
    }

    // ---- HTTP --------------------------------------------------------------------------------------

    private static function graph(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withToken((string) config('services.whatsapp.token'))->timeout(60);
    }

    private static function url(string $path): string
    {
        return config('services.meta.graph').$path;
    }

    private static function fail(\Illuminate\Http\Client\Response $response): void
    {
        if ($response->failed()) {
            throw new RuntimeException($response->json('error.error_user_msg') ?? $response->json('error.message') ?? 'WhatsApp error '.$response->status());
        }
    }
}
