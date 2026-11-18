<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\WhatsAppChat;
use App\Models\WhatsAppMessage;
use App\Support\WhatsApp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/** Team inbox of the company WhatsApp number (Admins + people with the "WhatsApp inbox" permission) */
class WhatsAppController extends Controller
{
    private const FILE_RULES = ['file', 'max:16384', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx'];

    public function index(Request $request): View
    {
        $this->allow($request);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:60'], 'show' => ['nullable', 'in:all,unread,open']]);
        $q = trim($filters['q'] ?? '');
        $digits = preg_replace('/\D+/', '', $q);

        return view('whatsapp.index', [
            'chats' => WhatsAppChat::with('lastMessage.user:id,name')
                ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', '%'.$q.'%')
                    ->when(strlen($digits) >= 3, fn ($w) => $w->orWhere('wa_id', 'like', '%'.$digits.'%'))))
                ->when(($filters['show'] ?? '') === 'unread', fn ($query) => $query->where('unread', '>', 0))
                ->when(($filters['show'] ?? '') === 'open', fn ($query) => $query->where('last_inbound_at', '>', now()->subDay()))
                ->orderByDesc('last_message_at')->paginate(40)->withQueryString(),
            'filters' => $filters,
            'ready' => WhatsApp::ready(),
            'templates' => $this->templates(),
        ]);
    }

    public function show(Request $request, WhatsAppChat $chat): View
    {
        $this->allow($request);
        $chat->update(['unread' => 0]);

        return view('whatsapp.show', ['chat' => $chat, 'messages' => $this->recent($chat), 'templates' => $this->templates()]);
    }

    /** The chat screen asks every few seconds; the list is sent again only when something changed */
    public function poll(Request $request, WhatsAppChat $chat): JsonResponse
    {
        $this->allow($request);
        $messages = $this->recent($chat);
        $sig = $messages->max('id').'-'.$messages->max('updated_at');
        if ($chat->unread) {
            $chat->update(['unread' => 0]);
        }

        return response()->json($sig === $request->query('sig') ? ['sig' => $sig] : ['sig' => $sig,
            'html' => view('whatsapp._messages', ['messages' => $messages])->render(),
            'window' => $chat->fresh()->windowEndsAt()?->toIso8601String()]);
    }

    public function send(Request $request, WhatsAppChat $chat): RedirectResponse
    {
        $user = $this->allow($request);
        $data = $request->validate(['text' => ['nullable', 'string', 'max:4000', 'required_without:file'], 'file' => array_merge(['nullable'], self::FILE_RULES)]);
        try {
            $data['file'] ?? null
                ? WhatsApp::sendFile($chat, $data['file'], $data['text'] ?? null, $user)
                : WhatsApp::sendText($chat, $data['text'], $user);
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['text' => $e->getMessage()]);
        }

        return redirect()->route('whatsapp.show', $chat);
    }

    /** Template to a chat whose 24-hour window is closed */
    public function template(Request $request, WhatsAppChat $chat): RedirectResponse
    {
        return $this->sendTemplate($request, $chat);
    }

    /** "New WhatsApp": a template to a number (a first message must be a template) */
    public function newChat(Request $request): RedirectResponse
    {
        return $this->sendTemplate($request, null);
    }

    private function sendTemplate(Request $request, ?WhatsAppChat $chat): RedirectResponse
    {
        $user = $this->allow($request);
        $data = $request->validate([
            'number' => [$chat ? 'nullable' : 'required', 'string', 'max:25'],
            'name' => ['nullable', 'string', 'max:80'],
            'template' => ['required', 'string', 'max:200'],
            'params' => ['nullable', 'array', 'max:10'],
            'params.*' => ['nullable', 'string', 'max:500'],
            'file' => array_merge(['nullable'], self::FILE_RULES),
        ]);
        $template = collect($this->templates())->firstWhere('key', $data['template']);
        if (! $template) {
            return back()->withInput()->withErrors(['template' => 'Pick an approved template (Meta may still be reviewing it).']);
        }
        $params = array_slice(array_values($data['params'] ?? []), 0, $template['params']);
        if (count(array_filter($params, fn ($p) => trim((string) $p) !== '')) < $template['params']) {
            return back()->withInput()->withErrors(['template' => 'Fill all '.$template['params'].' blank(s) of the template.']);
        }
        if (! $chat) {
            $waId = WhatsAppChat::normalise($data['number']);
            if (strlen($waId) < 11 || strlen($waId) > 15) {
                return back()->withInput()->withErrors(['number' => 'Type the number with country code, e.g. 98908 40101 or +971 50 123 4567.']);
            }
            $chat = WhatsAppChat::firstOrCreate(['wa_id' => $waId], ['name' => $data['name'] ?? null]);
        }
        try {
            WhatsApp::sendTemplate($chat, $template, $params, $data['file'] ?? null, $user);
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['template' => $e->getMessage()]);
        }

        return redirect()->route('whatsapp.show', $chat)->with('status', 'Template sent.');
    }

    public function media(Request $request, WhatsAppMessage $message): BinaryFileResponse
    {
        $this->allow($request);
        abort_unless($message->hasFile(), 404);
        try {
            $path = WhatsApp::mediaPath($message);
        } catch (Throwable $e) {
            report($e);
            abort(404, 'WhatsApp file is not available any more.');
        }
        $name = $message->file_name ?: 'whatsapp-'.$message->id.'.'.Str::afterLast($path, '.');

        return $request->boolean('download')
            ? response()->download(Storage::disk('local')->path($path), $name)
            : response()->file(Storage::disk('local')->path($path), ['Content-Type' => $message->mime ?: 'application/octet-stream']);
    }

    // ---- Admin > WhatsApp ---------------------------------------------------------------------------

    public function settings(): View
    {
        $info = null;
        $error = null;
        if (WhatsApp::ready()) {
            try {
                $info = WhatsApp::phoneInfo();
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return view('admin.whatsapp', ['ready' => WhatsApp::ready(), 'info' => $info, 'error' => $error,
            'webhook' => route('whatsapp.webhook'), 'hasVerifyToken' => (bool) config('services.whatsapp.verify_token'),
            'chats' => WhatsAppChat::count(), 'lastIn' => WhatsAppMessage::where('direction', 'in')->max('sent_at')]);
    }

    /** Subscribe our app to the number's webhooks and ask for the phone's contacts + chat history */
    public function connect(Request $request): RedirectResponse
    {
        abort_unless(WhatsApp::ready(), 422, 'Put WHATSAPP_TOKEN, WHATSAPP_WABA_ID and WHATSAPP_PHONE_ID in the server .env first.');
        try {
            WhatsApp::subscribe();
            $sync = $request->boolean('sync') ? WhatsApp::requestSync() : [];
            WhatsApp::templates(true);
        } catch (Throwable $e) {
            return back()->withErrors(['whatsapp' => 'Meta said: '.$e->getMessage()]);
        }

        return back()->with('status', 'Webhook subscribed.'.($sync
            ? ' Contacts sync: '.$sync['smb_app_state_sync'].'. Chat history: '.$sync['history'].' (it comes in over a few minutes).' : ''));
    }

    private function allow(Request $request): \App\Models\User
    {
        abort_unless($request->user()->hasPermission(Permission::WhatsAppChat), 403);

        return $request->user();
    }

    private function recent(WhatsAppChat $chat)
    {
        return $chat->messages()->with('user:id,name')->latest('sent_at')->latest('id')->limit(150)->get()->reverse()->values()
            ->each(fn ($m) => $m->setRelation('chat', $chat));
    }

    private function templates(): array
    {
        if (! WhatsApp::ready()) {
            return [];
        }

        return rescue(fn () => WhatsApp::templates(), [], true);
    }
}
