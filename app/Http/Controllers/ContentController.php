<?php

namespace App\Http\Controllers;

use App\Enums\ContentStatus;
use App\Enums\PostType;
use App\Enums\Role;
use App\Models\Content;
use App\Models\ContentComment;
use App\Models\ContentMedia;
use App\Models\Task;
use App\Models\User;
use App\Support\Notify;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ContentController extends Controller
{
    /** Approvals list: Approver ko apne pending, Admin/Manager ko sab */
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->hasRole('admin', 'manager', 'approver'), 403);

        $contents = Content::query()
            ->with(['task.item.calendar.client', 'task.assignee', 'approver'])
            ->when($user->role === Role::Approver, fn ($q) => $q->where('approver_id', $user->id))
            ->when($request->status ?? 'pending', fn ($q, $s) => match ($s) {
                'all' => $q,
                'pending' => $q->whereIn('status', [ContentStatus::Submitted, ContentStatus::Resubmitted]),
                default => $q->where('status', $s),
            })
            ->latest('updated_at')
            ->paginate(30)
            ->withQueryString();

        return view('contents.index', ['contents' => $contents, 'statuses' => ContentStatus::cases()]);
    }

    /** Task par ek post type ka pehla upload (V1) */
    public function store(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('upload', [Content::class, $task]);

        $postType = $request->validate([
            'post_type' => ['required', Rule::in($task->item->post_types), Rule::unique('contents')->where('task_id', $task->id)],
        ], ['post_type.unique' => 'Is post type ka content pehle se upload hai.'])['post_type'];

        $data = $request->validate([
            'approver_id' => ['required', Rule::in(User::where('role', Role::Approver)->where('is_active', true)->pluck('id'))],
            ...$this->versionRules(Content::typeOf($postType)),
        ]);

        $content = DB::transaction(function () use ($request, $task, $postType, $data) {
            $content = $task->contents()->create([
                'post_type' => $postType,
                'approver_id' => $data['approver_id'],
                'status' => ContentStatus::Submitted,
            ]);
            $this->storeVersion($request, $content, 1, $data);

            // Task tab "submitted" jab item ke saare post types upload ho jayein
            if ($task->contents()->count() === count($task->item->post_types)) {
                $task->update(['status' => 'submitted', 'submitted_at' => $task->submitted_at ?? now()]);
            }

            return $content;
        });

        Notify::send([$content->approver], 'Content approval ke liye', $this->title($content).' review karein.', route('contents.show', $content));

        return redirect()->route('contents.show', $content)->with('status', 'Content upload ho gaya, Approver ko bhej diya.');
    }

    public function show(Request $request, Content $content): View
    {
        Gate::authorize('view', $content);

        $content->load(['task.item.calendar.client', 'task.assignee', 'approver', 'versions.media', 'versions.submitter', 'comments.user', 'comments.version', 'comments.fixer']);
        $version = $content->versions->firstWhere('number', (int) $request->query('v', $content->current_version))
            ?? $content->versions->last();

        return view('contents.show', [
            'content' => $content,
            'version' => $version,
            // @mention hints
            'handles' => User::where('role', '!=', Role::Client)->where('is_active', true)->orderBy('name')->get()->map->handle(),
        ]);
    }

    /** Revision ke baad naya version (V2, V3...) */
    public function resubmit(Request $request, Content $content): RedirectResponse
    {
        Gate::authorize('resubmit', $content);

        $data = $request->validate($this->versionRules($content->type()));

        DB::transaction(function () use ($request, $content, $data) {
            $number = $content->current_version + 1;
            $this->storeVersion($request, $content, $number, $data);
            $content->update(['current_version' => $number, 'status' => ContentStatus::Resubmitted]);
        });

        Notify::send([$content->approver], 'V'.$content->current_version.' resubmit hua', $this->title($content).' ka naya version review karein.', route('contents.show', $content));

        return redirect()->route('contents.show', $content)->with('status', 'Naya version bhej diya.');
    }

    public function review(Request $request, Content $content): RedirectResponse
    {
        Gate::authorize('review', $content);

        $data = $request->validate([
            'action' => ['required', Rule::in(['approved', 'revision', 'rejected'])],
            'body' => ['required_if:action,revision', 'nullable', 'string', 'max:5000'],
        ], ['body.required_if' => 'Revision ke liye comment zaroori hai.']);

        DB::transaction(function () use ($request, $content, $data) {
            $content->update(['status' => match ($data['action']) {
                'approved' => ContentStatus::Approved,
                'revision' => ContentStatus::RevisionRequired,
                'rejected' => ContentStatus::Rejected,
            }]);
            $this->log($request, $content, $data['action'], $data['body'] ?? null);
        });

        $url = route('contents.show', $content);
        $label = ['approved' => 'Approved ✔', 'revision' => 'Revision chahiye', 'rejected' => 'Rejected'][$data['action']];
        Notify::send([$content->task->assignee], "Content: $label", $this->title($content).($data['body'] ? ' — '.$data['body'] : ''), $url);
        Notify::send(Notify::mentioned($data['body'] ?? null), 'Aapko mention kiya', $request->user()->name.': '.$data['body'], $url);

        return back()->with('status', 'Review save ho gaya.');
    }

    /** Normal comment: carousel par slide number, reel par timestamp (m:ss) */
    public function comment(Request $request, Content $content): RedirectResponse
    {
        Gate::authorize('comment', $content);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'version' => ['required', 'integer', Rule::exists('content_versions', 'number')->where('content_id', $content->id)],
            'slide' => ['nullable', 'integer', 'min:1', 'max:10'],
            'at' => ['nullable', 'regex:/^\d{1,3}:[0-5]\d$/'],
        ], ['at.regex' => 'Timestamp m:ss format mein likho, jaise 0:15']);

        $at = null;
        if (! empty($data['at'])) {
            [$m, $s] = explode(':', $data['at']);
            $at = (int) $m * 60 + (int) $s;
        }

        $content->comments()->create([
            'content_version_id' => $content->versions()->where('number', $data['version'])->value('id'),
            'user_id' => $request->user()->id,
            'body' => $data['body'],
            'slide' => $data['slide'] ?? null,
            'at_seconds' => $at,
        ]);

        $url = route('contents.show', [$content, 'v' => $data['version']]);
        $mentioned = Notify::mentioned($data['body']);
        Notify::send($mentioned, 'Aapko mention kiya', $request->user()->name.' ('.$this->title($content).'): '.$data['body'], $url);
        // Member + Approver ko bhi pata chale (jo mention mein already hain unhe dobara nahi)
        Notify::send(
            collect([$content->task->assignee, $content->approver])->reject(fn ($u) => $mentioned->contains('id', $u->id)),
            'Naya comment', $request->user()->name.' ('.$this->title($content).'): '.$data['body'], $url);

        return redirect()->route('contents.show', [$content, 'v' => $data['version']])->with('status', 'Comment add ho gaya.');
    }

    /** "Fixed ✔" checklist: member revision/comment ko fixed mark (ya unmark) karta hai */
    public function toggleFixed(Request $request, Content $content, ContentComment $comment): RedirectResponse
    {
        Gate::authorize('upload', [Content::class, $content->task]);
        abort_unless($comment->content_id === $content->id && in_array($comment->action, ['comment', 'revision'], true), 404);

        $comment->update($comment->fixed_at
            ? ['fixed_at' => null, 'fixed_by' => null]
            : ['fixed_at' => now(), 'fixed_by' => $request->user()->id]);

        return back();
    }

    /** Approved => Scheduled => Published (manual buttons) */
    public function markStatus(Request $request, Content $content): RedirectResponse
    {
        Gate::authorize('markStatus', $content);

        $next = $content->status === ContentStatus::Approved ? ContentStatus::Scheduled : ContentStatus::Published;
        $content->update(['status' => $next]);
        $this->log($request, $content, $next->value, null);

        return back()->with('status', 'Status: '.$next->label());
    }

    /** Private file ko login + permission check ke baad dikhana (video seek bhi chalta hai) */
    public function media(ContentMedia $media): BinaryFileResponse
    {
        Gate::authorize('view', $media->version->content);

        return response()->file(Storage::disk('local')->path($media->path));
    }

    /** "Instagram Reel · Acme · 05 Nov" */
    private function title(Content $content): string
    {
        $item = $content->task->item;

        return $content->typeLabel().' · '.$item->calendar->client->name.' · '.$item->post_date->format('d M');
    }

    private function versionRules(PostType $type): array
    {
        $image = ['image', 'mimes:jpg,jpeg,png,webp', 'max:10240']; // 10 MB

        return [
            'caption' => ['nullable', 'string', 'max:5000'],
            'hashtags' => ['nullable', 'string', 'max:2000'],
            ...match ($type) {
                PostType::Post => ['images' => ['required', 'array', 'size:1'], 'images.*' => $image],
                PostType::Carousel => ['images' => ['required', 'array', 'min:2', 'max:10'], 'images.*' => $image],
                PostType::Reel => [
                    'video' => ['required', 'file', 'mimetypes:video/mp4,video/quicktime', 'max:102400'], // 100 MB
                    'cover' => ['required', ...$image],
                ],
            },
        ];
    }

    private function storeVersion(Request $request, Content $content, int $number, array $data): void
    {
        $version = $content->versions()->create([
            'number' => $number,
            'caption' => $data['caption'] ?? null,
            'hashtags' => $data['hashtags'] ?? null,
            'submitted_by' => $request->user()->id,
        ]);

        $dir = "contents/{$content->id}/v{$number}";
        $files = $content->type() === PostType::Reel
            ? [['video', $request->file('video')], ['cover', $request->file('cover')]]
            : array_map(fn ($f) => ['image', $f], $request->file('images'));

        $stored = [];
        try {
            foreach ($files as $i => [$kind, $file]) {
                $stored[] = $path = $file->store($dir, 'local');
                $version->media()->create([
                    'kind' => $kind,
                    'path' => $path,
                    'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                    'sort_order' => $i + 1,
                ]);
            }
            $this->log($request, $content, 'submitted', null, $version->id);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($stored); // DB rollback ke saath files bhi hatao
            throw $e;
        }
    }

    private function log(Request $request, Content $content, string $action, ?string $body, ?int $versionId = null): void
    {
        $content->comments()->create([
            'content_version_id' => $versionId ?? $content->versions()->where('number', $content->current_version)->value('id'),
            'user_id' => $request->user()->id,
            'action' => $action,
            'body' => $body,
        ]);
    }
}
