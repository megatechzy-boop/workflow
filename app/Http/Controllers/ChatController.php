<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\ChatMessage;
use App\Models\Client;
use App\Models\Content;
use App\Models\User;
use App\Notifications\ChatPush;
use App\Support\Notify;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Group chat: "team" (all team), "client:{id}" (customer + team), "internal:{id}" (team only).
 * shortcut: no websockets on cPanel; the page polls every 3 s. Move to Pusher/Reverb if usage grows.
 */
class ChatController extends Controller
{
    private const WITH = ['user', 'replyTo.user', 'content.task.item.calendar'];

    public function index(Request $request): View
    {
        $user = $request->user();
        $rooms = ChatMessage::roomsFor($user);
        abort_if(! $rooms, 403, 'There is no chat group for you.');

        $room = $request->query('room', array_key_first($rooms));
        $this->authorizeRoom($request, $room);

        // "Discuss in chat" from a content page: the composer starts with that post's card
        $attach = $request->filled('content') ? Content::find($request->integer('content')) : null;
        $attach = $attach && $this->contentFitsRoom($request, $attach, $room) ? $attach : null;

        $search = trim((string) $request->query('q'));
        $messages = ChatMessage::with(self::WITH)->where('room', $room)
            ->when($search !== '', fn ($q) => $q->whereNull('deleted_at')->where('body', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->latest('id')->limit(100)->get()->reverse()->values();
        if ($search === '') {
            ChatMessage::markRead($user, $room);
        }

        // Group list like WhatsApp: last message preview, most recent group first
        $last = ChatMessage::with('user')->whereIn('id',
            ChatMessage::selectRaw('max(id)')->whereIn('room', array_keys($rooms))->groupBy('room'))->get()->keyBy('room');
        uksort($rooms, fn ($a, $b) => ($last[$b]->id ?? 0) <=> ($last[$a]->id ?? 0));

        return view('chat.index', [
            'rooms' => $rooms,
            'last' => $last->map(fn ($m) => [
                'who' => $m->user_id === $user->id ? 'You' : strtok($m->user->name, ' '),
                'text' => $m->deleted_at ? 'This message was deleted'
                    : ($m->body ?: ($m->isAudio() ? 'Voice note' : ($m->isImage() ? 'Photo' : $m->attachment_name))),
                'when' => $m->created_at->isToday() ? $m->created_at->format('h:i A')
                    : ($m->created_at->isYesterday() ? 'Yesterday' : $m->created_at->format('d/m/y')),
            ]),
            'room' => $room,
            'picked' => $request->has('room'), // phone: list screen until a group is opened
            'search' => $search,
            'unread' => ChatMessage::unreadCounts($user),
            'mutedRooms' => DB::table('chat_reads')->where('user_id', $user->id)->where('muted', true)->pluck('room')->all(),
            'canPin' => $user->role !== Role::Client,
            'canLinkPosts' => $this->canLinkPosts($user) && str_contains($room, ':'), // brand groups only
            'pinned' => $this->pinned($room, $user),
            'attach' => $attach ? $this->contentCard($attach, $user) : null,
            'messages' => $messages->map(fn ($m) => $this->present($m, $user)),
        ]);
    }

    /** Polling: new messages after ?after=ID, plus messages deleted in the last minutes (to update open screens) */
    public function poll(Request $request, string $room): JsonResponse
    {
        $this->authorizeRoom($request, $room);

        $messages = ChatMessage::with(self::WITH)->where('room', $room)
            ->where('id', '>', (int) $request->query('after', 0))->orderBy('id')->limit(100)->get();
        ChatMessage::markRead($request->user(), $room);

        return response()->json([
            'messages' => $messages->map(fn ($m) => $this->present($m, $request->user())),
            'deleted' => ChatMessage::where('room', $room)->where('deleted_at', '>=', now()->subMinutes(ChatMessage::DELETE_MINUTES + 5))->pluck('id'),
            'pinned' => $this->pinned($room, $request->user()),
        ]);
    }

    /** Pin / unpin (team only); one pinned message per group */
    public function pin(Request $request, ChatMessage $message): JsonResponse
    {
        $this->authorizeRoom($request, $message->room);
        abort_if($request->user()->role === Role::Client || $message->deleted_at, 403);

        $pin = ! $message->pinned_at;
        ChatMessage::where('room', $message->room)->whereNotNull('pinned_at')->update(['pinned_at' => null]);
        if ($pin) {
            $message->forceFill(['pinned_at' => now()])->save();
        }

        return response()->json(['pinned' => $this->pinned($message->room, $request->user())]);
    }

    /** Mute / unmute phone push for one group (per user) */
    public function mute(Request $request, string $room): JsonResponse
    {
        $this->authorizeRoom($request, $room);
        $key = ['user_id' => $request->user()->id, 'room' => $room];
        $muted = ! DB::table('chat_reads')->where($key)->value('muted');
        DB::table('chat_reads')->updateOrInsert($key, ['muted' => $muted]);

        return response()->json(['muted' => $muted]);
    }

    /** Posts of the group's brand, for the "link a post" picker (SMM / Ops / Admin) */
    public function posts(Request $request, string $room): JsonResponse
    {
        $this->authorizeRoom($request, $room);
        abort_unless($this->canLinkPosts($request->user()) && preg_match('/:(\d+)$/', $room, $m), 403);

        // shortcut: newest 200 posts of the brand, sorted in PHP; add a join if a brand ever has thousands
        return response()->json(Content::with('task.item.calendar')->latest('id')->limit(200)
            ->whereHas('task.item.calendar', fn ($q) => $q->where('client_id', $m[1]))->get()
            ->sortByDesc(fn ($c) => $c->task->item->post_date)->values()
            ->map(fn ($c) => $this->contentCard($c, $request->user()) + ['status' => $c->status->label()]));
    }

    private function canLinkPosts(User $user): bool
    {
        return in_array($user->role, [Role::SocialMediaManager, Role::Manager, Role::Admin], true);
    }

    /** "Photos & files" of a group */
    public function media(Request $request, string $room): JsonResponse
    {
        $this->authorizeRoom($request, $room);

        return response()->json(ChatMessage::with('user')->where('room', $room)->whereNull('deleted_at')
            ->whereNotNull('attachment_path')->latest('id')->limit(200)->get()
            ->map(fn ($m) => ['user' => $m->user->name, 'day' => $m->created_at->format('d M Y')] + $this->file($m)));
    }

    /** "Delete for everyone": own message, within DELETE_MINUTES; the file is removed too */
    public function destroy(Request $request, ChatMessage $message): JsonResponse
    {
        $this->authorizeRoom($request, $message->room);
        abort_unless($message->user_id === $request->user()->id, 403);
        abort_if($message->deleted_at || $message->created_at->lt(now()->subMinutes(ChatMessage::DELETE_MINUTES)), 422, 'This message can no longer be deleted.');

        if ($message->attachment_path) {
            Storage::disk('local')->delete($message->attachment_path);
        }
        $message->forceFill(['body' => null, 'attachment_path' => null, 'attachment_name' => null, 'attachment_mime' => null,
            'content_id' => null, 'pinned_at' => null, 'deleted_at' => now()])->save();

        return response()->json($this->present($message->load(self::WITH), $request->user()));
    }

    public function seen(Request $request, ChatMessage $message): JsonResponse
    {
        $this->authorizeRoom($request, $message->room);
        abort_unless($message->user_id === $request->user()->id, 403);

        return response()->json($message->seenBy()->pluck('name'));
    }

    public function store(Request $request, string $room): JsonResponse
    {
        $this->authorizeRoom($request, $room);

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:5000', 'required_without:file'],
            'file' => ['nullable', 'file', 'max:102400',
                'mimetypes:image/jpeg,image/png,image/webp,image/gif,application/pdf,video/mp4,video/quicktime,'
                .'audio/webm,video/webm,audio/mp4,audio/x-m4a,audio/aac,audio/mpeg,audio/ogg,'
                .'application/vnd.openxmlformats-officedocument.wordprocessingml.document,'
                .'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,'
                .'application/vnd.openxmlformats-officedocument.presentationml.presentation'],
            'reply_to_id' => ['nullable', 'integer'],
            'content_id' => ['nullable', 'integer'],
        ], [
            'body.required_without' => 'Send a message or a file.',
            'file.mimetypes' => 'Send a photo, video, voice note, PDF or Word/Excel/PowerPoint file.',
        ]);

        $file = $request->file('file');
        $content = isset($data['content_id']) ? Content::find($data['content_id']) : null;
        abort_if($content && ! $this->contentFitsRoom($request, $content, $room), 403);
        $message = ChatMessage::create([
            'content_id' => $content?->id,
            'room' => $room,
            'user_id' => $request->user()->id,
            // reply only to a message of the same room
            'reply_to_id' => isset($data['reply_to_id']) ? ChatMessage::where('room', $room)->whereKey($data['reply_to_id'])->value('id') : null,
            'body' => $data['body'] ?? null,
            'attachment_path' => $file?->store('chat/'.str_replace(':', '-', $room), 'local'),
            'attachment_name' => $file ? mb_substr($file->getClientOriginalName(), 0, 255) : null,
            'attachment_mime' => $file?->getMimeType(),
        ]);
        ChatMessage::markRead($request->user(), $room);

        // @mention => only members of this room
        $members = ChatMessage::membersOf($room);
        $mentioned = Notify::mentioned($message->body)->filter(fn ($u) => $members->contains('id', $u->id));
        $roomName = ChatMessage::roomsFor($request->user())[$room] ?? $room;
        $url = route('chat.index', ['room' => $room]);
        Notify::send($mentioned, 'You were mentioned in chat', $request->user()->name.' ('.$roomName.'): '.$message->body, $url);

        // Everyone else in the group gets a phone push (mentioned users already got one above),
        // except who muted this group, and customers at night (they see the unread badge next morning)
        $text = $message->body ?? ($message->isAudio() ? 'Voice note' : ($message->isImage() ? 'Photo' : $message->attachment_name));
        $muted = DB::table('chat_reads')->where('room', $room)->where('muted', true)->pluck('user_id');
        $quiet = ChatMessage::isQuietHours();
        $others = $members->reject(fn ($u) => $u->id === $request->user()->id || $mentioned->contains('id', $u->id)
            || $muted->contains($u->id) || ($quiet && $u->role === Role::Client));
        try {
            Notification::send($others, new ChatPush($request->user()->name.' · '.$roomName, $text, $url, $room));
        } catch (\Throwable $e) {
            report($e); // a failed push must not lose the sent message
        }

        return response()->json($this->present($message->load(self::WITH), $request->user()), 201);
    }

    public function attachment(Request $request, ChatMessage $message): BinaryFileResponse
    {
        $this->authorizeRoom($request, $message->room);
        abort_unless($message->attachment_path, 404);
        $path = Storage::disk('local')->path($message->attachment_path);

        return $request->boolean('download')
            ? response()->download($path, $message->attachment_name)
            : response()->file($path);
    }

    private function authorizeRoom(Request $request, string $room): void
    {
        abort_unless(ChatMessage::canAccess($request->user(), $room), 403);
        // client:{id} / internal:{id} must point to an existing brand
        if (preg_match('/:(\d+)$/', $room, $m)) {
            abort_unless(Client::whereKey($m[1])->exists(), 404);
        }
    }

    /** Content can be linked in this room: the user may see it, and it belongs to the room's brand */
    private function contentFitsRoom(Request $request, Content $content, string $room): bool
    {
        if (! $request->user()->can('view', $content)) {
            return false;
        }

        return ! preg_match('/:(\d+)$/', $room, $m) || (int) $m[1] === $content->task->item->calendar->client_id;
    }

    /** "5 Nov · Reel · Instagram" card; the link only for users allowed to open it */
    private function contentCard(Content $content, User $me): array
    {
        return [
            'id' => $content->id,
            'label' => $content->task->item->post_date->format('d M').' · '.$content->typeLabel(),
            'url' => $me->can('view', $content) ? route('contents.show', $content) : null,
        ];
    }

    private function pinned(string $room, User $me): ?array
    {
        $m = ChatMessage::with(self::WITH)->where('room', $room)->whereNotNull('pinned_at')->first();

        return $m ? $this->present($m, $me) : null;
    }

    private function file(ChatMessage $m): array
    {
        return [
            'name' => $m->attachment_name,
            'url' => route('chat.attachment', $m),
            'download' => route('chat.attachment', [$m, 'download' => 1]),
            'image' => $m->isImage(),
            'audio' => $m->isAudio(),
        ];
    }

    /** Data for JS (the view escapes text) */
    private function present(ChatMessage $m, User $me): array
    {
        return [
            'id' => $m->id,
            'mine' => $m->user_id === $me->id,
            'pinned' => (bool) $m->pinned_at,
            'content' => $m->content && ! $m->deleted_at ? $this->contentCard($m->content, $me) : null,
            'user' => $m->user->name,
            'role' => $m->user->role->label(),
            'initials' => collect(explode(' ', $m->user->name))->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join(''),
            'body' => $m->body,
            'deleted' => (bool) $m->deleted_at,
            'canDelete' => $m->user_id === $me->id && ! $m->deleted_at && $m->created_at->gte(now()->subMinutes(ChatMessage::DELETE_MINUTES)),
            'day' => $m->created_at->isToday() ? 'Today' : ($m->created_at->isYesterday() ? 'Yesterday' : $m->created_at->format('d M Y')),
            'time' => $m->created_at->format('h:i A'),
            'reply' => $m->replyTo ? [
                'id' => $m->replyTo->id,
                'user' => $m->replyTo->user->name,
                'text' => $m->replyTo->deleted_at ? 'Deleted message' : ($m->replyTo->body ?: ($m->replyTo->attachment_name ?? '')),
            ] : null,
            'file' => $m->attachment_path ? $this->file($m) : null,
        ];
    }
}
