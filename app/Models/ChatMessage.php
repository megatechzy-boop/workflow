<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ChatMessage extends Model
{
    protected $fillable = ['room', 'user_id', 'reply_to_id', 'content_id', 'body', 'attachment_path', 'attachment_name', 'attachment_mime'];

    protected $casts = ['deleted_at' => 'datetime', 'pinned_at' => 'datetime'];

    /** Own messages can be deleted for everyone within this many minutes */
    public const DELETE_MINUTES = 10;

    /** Customers get no chat push from 22:00 to 09:00 (app timezone) */
    public static function isQuietHours(): bool
    {
        return now()->hour >= 22 || now()->hour < 9;
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    /**
     * Rooms of a user: ['team' => 'All team', 'client:3' => 'Acme · Client group', 'internal:3' => 'Acme · Internal']
     * Customers only get their own brand's client group.
     */
    public static function roomsFor(User $user): array
    {
        if ($user->role === Role::Client) {
            $client = $user->client;

            return $client ? ['client:'.$client->id => $client->name.' · Chat with team'] : [];
        }

        $rooms = ['team' => 'All team (internal)'];
        foreach (Client::where('is_active', true)->orderBy('name')->get(['id', 'name']) as $c) {
            $rooms['client:'.$c->id] = $c->name.' · Client group';
            $rooms['internal:'.$c->id] = $c->name.' · Internal';
        }

        return $rooms;
    }

    public static function canAccess(User $user, string $room): bool
    {
        if ($user->role === Role::Client) {
            return $room === 'client:'.$user->client_id && $user->client_id !== null;
        }

        return $room === 'team' || preg_match('/^(client|internal):\d+$/', $room) === 1;
    }

    /** Members of a room (for mentions / notifications) */
    public static function membersOf(string $room): Collection
    {
        $staff = User::where('is_active', true)->where('role', '!=', Role::Client);
        if (str_starts_with($room, 'client:')) {
            $clientId = (int) substr($room, 7);

            return $staff->get()->merge(User::where('is_active', true)->where('role', Role::Client)->where('client_id', $clientId)->get());
        }

        return $staff->get();
    }

    /** Unread messages per room (excluding own messages) */
    public static function unreadCounts(User $user): array
    {
        $rooms = array_keys(static::roomsFor($user));
        if (! $rooms) {
            return [];
        }
        $read = DB::table('chat_reads')->where('user_id', $user->id)->pluck('last_read_id', 'room');

        return static::query()
            ->where('user_id', '!=', $user->id)
            ->where(function ($q) use ($rooms, $read) {
                foreach ($rooms as $room) {
                    $q->orWhere(fn ($w) => $w->where('room', $room)->where('id', '>', $read[$room] ?? 0));
                }
            })
            ->selectRaw('room, COUNT(*) as n')->groupBy('room')->pluck('n', 'room')->map(fn ($n) => (int) $n)->all();
    }

    public static function markRead(User $user, string $room): void
    {
        $last = static::where('room', $room)->max('id') ?? 0;
        DB::table('chat_reads')->updateOrInsert(['user_id' => $user->id, 'room' => $room], ['last_read_id' => $last]);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->attachment_mime, 'image/');
    }

    /** Voice notes: Chrome records webm (detected as video/webm), so the name decides too */
    public function isAudio(): bool
    {
        return str_starts_with((string) $this->attachment_mime, 'audio/') || str_starts_with((string) $this->attachment_name, 'Voice note');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    /** Members (other than the sender) whose read marker has passed this message */
    public function seenBy(): Collection
    {
        $ids = DB::table('chat_reads')->where('room', $this->room)->where('last_read_id', '>=', $this->id)
            ->where('user_id', '!=', $this->user_id)->pluck('user_id');

        return static::membersOf($this->room)->whereIn('id', $ids)->values();
    }
}
