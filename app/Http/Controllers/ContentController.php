<?php

namespace App\Http\Controllers;

use App\Enums\ContentStatus;
use App\Enums\Permission;
use App\Enums\PostType;
use App\Enums\Role;
use App\Models\Content;
use App\Models\ContentComment;
use App\Models\ContentMedia;
use App\Models\ContentVersion;
use App\Models\PointEntry;
use App\Models\Task;
use App\Models\User;
use App\Support\Notify;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ContentController extends Controller
{
    /** Approvals list: approvers see theirs, view-all users see everything, Customers their brand */
    public function index(Request $request): View
    {
        $user = $request->user();
        $isCustomer = $user->role === Role::Client;
        abort_unless($isCustomer || $user->hasPermission(Permission::ApproveContent) || $user->hasPermission(Permission::ViewAllTasks), 403);

        $contents = Content::query()
            ->with(['task.item.calendar.client', 'task.assignee', 'approver'])
            ->when($isCustomer, fn ($q) => $q
                ->whereHas('task.item.calendar', fn ($c) => $c->where('client_id', $user->client_id))
                ->whereIn('status', array_filter(ContentStatus::cases(), fn ($s) => $s->visibleToCustomer())))
            ->when(! $isCustomer && ! $user->hasPermission(Permission::ViewAllTasks), fn ($q) => $q->where('approver_id', $user->id))
            ->when($request->status ?? 'pending', fn ($q, $s) => match ($s) {
                'all' => $q,
                'pending' => $q->whereIn('status', $isCustomer ? [ContentStatus::ClientReview] : [ContentStatus::Submitted, ContentStatus::Resubmitted]),
                default => $q->where('status', $s),
            })
            ->latest('updated_at')
            ->paginate(30)
            ->withQueryString();

        return view('contents.index', ['contents' => $contents, 'statuses' => ContentStatus::cases()]);
    }

    /** First upload (V1) of one format on a task */
    public function store(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('upload', [Content::class, $task]);

        // One upload per format (post / carousel / reel) covers every platform of the row.
        // Uploader may only send formats of their role; the task is done when the assignee's formats are in.
        $allowed = $task->item->formatsFor($request->user()->role);
        $formats = $task->item->formatsFor($task->assignee->role);
        $postType = $request->validate([
            'post_type' => ['required', Rule::in(array_map(fn ($f) => $f->value, $allowed))],
        ], ['post_type.in' => 'This format is not part of your role.'])['post_type'];
        if ($task->contents->contains(fn ($c) => $c->type()->value === $postType)) {
            throw ValidationException::withMessages(['post_type' => 'Content for this format is already uploaded.']);
        }

        $data = $request->validate([
            // Not shown to Designers/Editors; picked automatically below
            'approver_id' => ['nullable', Rule::in(User::withPermission(Permission::ApproveContent)->pluck('id'))],
            ...$this->versionRules(Content::typeOf($postType)),
        ]);
        $approverId = $data['approver_id'] ?? $this->defaultApprover($task);
        if (! $approverId) {
            throw ValidationException::withMessages(['approver_id' => 'No Social Media Manager is available to review this. Please ask an Admin.']);
        }

        $content = DB::transaction(function () use ($request, $task, $postType, $data, $formats, $approverId) {
            $content = $task->contents()->create([
                'post_type' => $postType,
                'approver_id' => $approverId,
                'status' => ContentStatus::Submitted,
            ]);
            $this->storeVersion($request, $content, 1, $data);

            // The task becomes "submitted" once every format of the item is uploaded
            $uploaded = $task->contents()->get()->map(fn ($c) => $c->type())->unique();
            if (collect($formats)->every(fn ($f) => $uploaded->contains($f))) {
                $task->update(['status' => 'submitted', 'submitted_at' => $task->submitted_at ?? now()]);
                PointEntry::forSubmittedTask($task);
            }

            return $content;
        });

        Notify::send([$content->approver], 'Content needs approval', $this->title($content).' is waiting for your review.', route('contents.show', $content));

        return redirect()->route('contents.show', $content)->with('status', 'Content uploaded and sent to the Approver.');
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
            // @mention hints (Customers do not get the team list)
            'handles' => $request->user()->role === Role::Client ? collect()
                : User::where('role', '!=', Role::Client)->where('is_active', true)->orderBy('name')->get()->map->handle(),
        ]);
    }

    /** New version after a revision (V2, V3...) */
    public function resubmit(Request $request, Content $content): RedirectResponse
    {
        Gate::authorize('resubmit', $content);

        $data = $request->validate($this->versionRules($content->type()));

        DB::transaction(function () use ($request, $content, $data) {
            $number = $content->current_version + 1;
            // Keep the SMM's caption/hashtags on the new version
            $previous = $content->versions()->where('number', $content->current_version)->first();
            $this->storeVersion($request, $content, $number, ['caption' => $previous?->caption, 'hashtags' => $previous?->hashtags] + $data);
            $content->update(['current_version' => $number, 'status' => ContentStatus::Resubmitted]);
        });

        Notify::send([$content->approver], 'V'.$content->current_version.' resubmitted', $this->title($content).': new version is waiting for your review.', route('contents.show', $content));

        return redirect()->route('contents.show', $content)->with('status', 'New version submitted.');
    }

    public function review(Request $request, Content $content): RedirectResponse
    {
        Gate::authorize('review', $content);

        $data = $request->validate([
            'action' => ['required', Rule::in(['approved', 'revision', 'rejected'])],
            'body' => ['required_if:action,revision', 'nullable', 'string', 'max:5000'],
        ], ['body.required_if' => 'A comment is required for a revision.']);

        DB::transaction(function () use ($request, $content, $data) {
            $content->update(['status' => match ($data['action']) {
                'approved' => ContentStatus::ClientReview, // after the SMM, the Customer gives final approval
                'revision' => ContentStatus::RevisionRequired,
                'rejected' => ContentStatus::Rejected,
            }]);
            $this->log($request, $content, $data['action'], $data['body'] ?? null);
        });

        $url = route('contents.show', $content);
        $label = ['approved' => 'Approved by SMM, now with the Customer', 'revision' => 'Revision required', 'rejected' => 'Rejected'][$data['action']];
        Notify::send([$content->task->assignee], "Content: $label", $this->title($content).($data['body'] ? ' — '.$data['body'] : ''), $url);
        Notify::send(Notify::mentioned($data['body'] ?? null), 'You were mentioned', $request->user()->name.': '.$data['body'], $url);
        if ($data['action'] === 'approved') {
            Notify::send($this->customers($content), 'Content ready for your approval', $this->title($content).' is ready for your approval.', $url);
        }

        return back()->with('status', 'Review saved.');
    }

    /** Customer final review: Approve or Revision (comment required) */
    public function customerReview(Request $request, Content $content): RedirectResponse
    {
        Gate::authorize('customerReview', $content);

        $data = $request->validate([
            'action' => ['required', Rule::in(['approved', 'revision'])],
            'body' => ['required_if:action,revision', 'nullable', 'string', 'max:5000'],
        ], ['body.required_if' => 'A comment is required for a revision.']);

        DB::transaction(function () use ($request, $content, $data) {
            $approved = $data['action'] === 'approved';
            $content->update(['status' => $approved ? ContentStatus::Approved : ContentStatus::RevisionRequired]);
            $this->log($request, $content, $approved ? 'customer_approved' : 'customer_revision', $data['body'] ?? null);
            if ($approved) {
                PointEntry::forApprovedContent($content);
            }
        });

        $title = $data['action'] === 'approved' ? 'Customer approved' : 'Customer requested changes';
        Notify::send([$content->task->assignee, $content->approver], $title,
            $this->title($content).($data['body'] ? ' — '.$data['body'] : ''), route('contents.show', $content));

        return back()->with('status', 'Thank you! Your review has been saved.');
    }

    private function customers(Content $content)
    {
        return User::where('role', Role::Client)->where('client_id', $content->task->item->calendar->client_id)->get();
    }

    /** Plain comment: slide number on carousels, timestamp (m:ss) on reels */
    public function comment(Request $request, Content $content): RedirectResponse
    {
        Gate::authorize('comment', $content);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'version' => ['required', 'integer', Rule::exists('content_versions', 'number')->where('content_id', $content->id)],
            'slide' => ['nullable', 'integer', 'min:1', 'max:10'],
            'at' => ['nullable', 'regex:/^\d{1,3}:[0-5]\d$/'],
        ], ['at.regex' => 'Use the m:ss format for timestamps, e.g. 0:15']);

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
        Notify::send($mentioned, 'You were mentioned', $request->user()->name.' ('.$this->title($content).'): '.$data['body'], $url);
        // Also tell the member + approver (skip anyone already mentioned)
        Notify::send(
            collect([$content->task->assignee, $content->approver])->reject(fn ($u) => $mentioned->contains('id', $u->id)),
            'New comment', $request->user()->name.' ('.$this->title($content).'): '.$data['body'], $url);

        return redirect()->route('contents.show', [$content, 'v' => $data['version']])->with('status', 'Comment added.');
    }

    /** "Fixed" checklist: the member marks (or unmarks) a revision/comment as fixed */
    public function toggleFixed(Request $request, Content $content, ContentComment $comment): RedirectResponse
    {
        Gate::authorize('upload', [Content::class, $content->task]);
        abort_unless($comment->content_id === $content->id && $comment->isChecklistItem(), 404);

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

    /** Serve a private file after login + permission check (video seeking works) */
    public function media(ContentMedia $media): BinaryFileResponse
    {
        Gate::authorize('view', $media->version->content);

        return response()->file(Storage::disk('local')->path($media->path));
    }

    /** Download with a clean name: "acme-2026-11-05-carousel-slide-2.jpg" */
    public function download(ContentMedia $media): BinaryFileResponse
    {
        $content = $media->version->content;
        Gate::authorize('view', $content);

        $item = $content->task->item;
        $part = match ($media->kind) {
            'image' => $content->type() === PostType::Carousel ? 'slide-'.$media->sort_order : 'image',
            default => $media->kind, // video / cover
        };
        $name = Str::slug($item->calendar->client->name).'-'.$item->post_date->format('Y-m-d').'-'
            .$content->type()->value.'-'.$part.'.'.pathinfo($media->path, PATHINFO_EXTENSION);

        return response()->download(Storage::disk('local')->path($media->path), $name);
    }

    /** "Instagram Reel · Acme · 05 Nov" */
    private function title(Content $content): string
    {
        $item = $content->task->item;

        return $content->typeLabel().' · '.$item->calendar->client->name.' · '.$item->post_date->format('d M');
    }

    /** Wrong file? Before review the uploader swaps the files of the current version (same V number) */
    public function replace(Request $request, Content $content): RedirectResponse
    {
        Gate::authorize('replace', $content);

        $request->validate($this->versionRules($content->type()));
        $version = $content->versions()->where('number', $content->current_version)->firstOrFail();
        $oldPaths = $version->media()->pluck('path')->all();

        DB::transaction(function () use ($request, $content, $version) {
            $version->media()->delete();
            $this->storeMedia($request, $content, $version, 'replaced');
        });
        Storage::disk('local')->delete($oldPaths); // only after the new files are safely saved

        Notify::send([$content->approver], 'Files replaced', $this->title($content).': V'.$version->number.' files were replaced before review.', route('contents.show', $content));

        return back()->with('status', 'Files replaced.');
    }

    /** SMM sets caption + hashtags on the latest version (Designers/Editors only upload files) */
    public function updateCaption(Request $request, Content $content): RedirectResponse
    {
        Gate::authorize('editCaption', $content);

        $data = $request->validate([
            'caption' => ['nullable', 'string', 'max:5000'],
            'hashtags' => ['nullable', 'string', 'max:2000'],
        ]);
        $content->versions()->where('number', $content->current_version)->update($data);

        return back()->with('status', 'Caption and hashtags saved.');
    }

    /** Calendar creator if they can approve content, otherwise the first active SMM (Admin as last resort) */
    private function defaultApprover(Task $task): ?int
    {
        $approvers = User::withPermission(Permission::ApproveContent);
        $creatorId = $task->item->calendar->created_by;

        return $approvers->firstWhere('id', $creatorId)?->id
            ?? $approvers->firstWhere('role', Role::SocialMediaManager)?->id
            ?? $approvers->first()?->id;
    }

    private function versionRules(PostType $type): array
    {
        $image = ['image', 'mimes:jpg,jpeg,png,webp', 'max:10240']; // 10 MB

        return [
            ...match ($type) {
                PostType::Post => ['images' => ['required', 'array', 'size:1'], 'images.*' => $image],
                PostType::Carousel => ['images' => ['required', 'array', 'min:2', 'max:10'], 'images.*' => $image],
                PostType::Reel => [
                    'video' => ['required', 'file', 'mimetypes:video/mp4,video/quicktime', 'max:'.config('filesystems.max_upload_mb') * 1024], // MAX_UPLOAD_MB
                    'cover' => ['nullable', ...$image], // optional reel cover
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

        $this->storeMedia($request, $content, $version, 'submitted');
    }

    /** Save the uploaded files on a version (cover is optional for reels) */
    private function storeMedia(Request $request, Content $content, ContentVersion $version, string $action): void
    {
        $dir = "contents/{$content->id}/v{$version->number}";
        $files = $content->type() === PostType::Reel
            ? array_filter([['video', $request->file('video')], ['cover', $request->file('cover')]], fn ($f) => $f[1])
            : array_map(fn ($f) => ['image', $f], $request->file('images'));

        $stored = [];
        try {
            foreach (array_values($files) as $i => [$kind, $file]) {
                $stored[] = $path = $file->store($dir, 'local');
                $version->media()->create([
                    'kind' => $kind,
                    'path' => $path,
                    'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                    'sort_order' => $i + 1,
                ]);
            }
            $this->log($request, $content, $action, null, $version->id);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($stored); // remove files together with the DB rollback
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
