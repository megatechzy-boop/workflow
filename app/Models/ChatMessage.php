<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ChatMessage extends Model
{
    protected $fillable = ['room', 'user_id', 'body', 'attachment_path', 'attachment_name', 'attachment_mime'];

    /**
     * User ke rooms: ['team' => '👥 Sab team', 'client:3' => '🏷 Acme · Client group', 'internal:3' => '🔒 Acme · Internal']
     * Customer ko sirf apne brand ka client group.
     */
    public static function roomsFor(User $user): array
    {
        if ($user->role === Role::Client) {
            $client = $user->client;

            return $client ? ['client:'.$client->id => '🏷 '.$client->name.' · Team ke saath'] : [];
        }

        $rooms = ['team' => '👥 Sab team (internal)'];
        foreach (Client::where('is_active', true)->orderBy('name')->get(['id', 'name']) as $c) {
            $rooms['client:'.$c->id] = '🏷 '.$c->name.' · Client group';
            $rooms['internal:'.$c->id] = '🔒 '.$c->name.' · Internal';
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

    /** Room ke log (mention / notification ke liye) */
    public static function membersOf(string $room): Collection
    {
        $staff = User::where('is_active', true)->where('role', '!=', Role::Client);
        if (str_starts_with($room, 'client:')) {
            $clientId = (int) substr($room, 7);

            return $staff->get()->merge(User::where('is_active', true)->where('role', Role::Client)->where('client_id', $clientId)->get());
        }

        return $staff->get();
    }

    /** Har room ke unread messages (apne bheje hue nahi) */
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
