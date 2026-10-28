<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\Client;
use App\Support\Notify;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Group chat: "team" (all team), "client:{id}" (customer + team), "internal:{id}" (team only).
 * shortcut: no websockets on cPanel; the page polls every 5 s. Move to Pusher/Reverb if usage grows.
 */
class ChatController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $rooms = ChatMessage::roomsFor($user);
        abort_if(! $rooms, 403, 'There is no chat group for you.');

        $room = $request->query('room', array_key_first($rooms));
        $this->authorizeRoom($request, $room);

        $messages = ChatMessage::with('user')->where('room', $room)->latest('id')->limit(100)->get()->reverse()->values();
        ChatMessage::markRead($user, $room);

        return view('chat.index', [
            'rooms' => $rooms,
            'room' => $room,
            'unread' => ChatMessage::unreadCounts($user),
            'messages' => $messages->map(fn ($m) => $this->present($m, $user->id)),
        ]);
    }

    /** Polling: messages after ?after=ID */
    public function poll(Request $request, string $room): JsonResponse
    {
        $this->authorizeRoom($request, $room);

        $messages = ChatMessage::with('user')->where('room', $room)
            ->where('id', '>', (int) $request->query('after', 0))->orderBy('id')->limit(100)->get();
        ChatMessage::markRead($request->user(), $room);

        return response()->json($messages->map(fn ($m) => $this->present($m, $request->user()->id)));
    }

    public function store(Request $request, string $room): JsonResponse
    {
        $this->authorizeRoom($request, $room);

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:5000', 'required_without:file'],
            'file' => ['nullable', 'file', 'max:102400',
                'mimetypes:image/jpeg,image/png,image/webp,image/gif,application/pdf,video/mp4,video/quicktime,'
                .'application/vnd.openxmlformats-officedocument.wordprocessingml.document,'
                .'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,'
                .'application/vnd.openxmlformats-officedocument.presentationml.presentation'],
        ], [
            'body.required_without' => 'Send a message or a file.',
            'file.mimetypes' => 'Send a photo, video, PDF or Word/Excel/PowerPoint file.',
        ]);

        $file = $request->file('file');
        $message = ChatMessage::create([
            'room' => $room,
            'user_id' => $request->user()->id,
            'body' => $data['body'] ?? null,
            'attachment_path' => $file?->store('chat/'.str_replace(':', '-', $room), 'local'),
            'attachment_name' => $file ? mb_substr($file->getClientOriginalName(), 0, 255) : null,
            'attachment_mime' => $file?->getMimeType(),
        ]);
        ChatMessage::markRead($request->user(), $room);

        // @mention => only members of this room
        $members = ChatMessage::membersOf($room)->pluck('id');
        Notify::send(
            Notify::mentioned($message->body)->filter(fn ($u) => $members->contains($u->id)),
            'You were mentioned in chat',
            $request->user()->name.' ('.(ChatMessage::roomsFor($request->user())[$room] ?? $room).'): '.$message->body,
            route('chat.index', ['room' => $room]));

        return response()->json($this->present($message->load('user'), $request->user()->id), 201);
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

    /** Data for JS (the view escapes text) */
    private function present(ChatMessage $m, int $me): array
    {
        return [
            'id' => $m->id,
            'mine' => $m->user_id === $me,
            'user' => $m->user->name,
            'role' => $m->user->role->label(),
            'body' => $m->body,
            'time' => $m->created_at->format('d M, h:i A'),
            'file' => $m->attachment_path ? [
                'name' => $m->attachment_name,
                'url' => route('chat.attachment', $m),
                'download' => route('chat.attachment', [$m, 'download' => 1]),
                'image' => $m->isImage(),
            ] : null,
        ];
    }
}
