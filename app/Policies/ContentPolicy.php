<?php

namespace App\Policies;

use App\Enums\ContentStatus;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Content;
use App\Models\Task;
use App\Models\User;

class ContentPolicy
{
    /** The agency team sees everything; Customers only their brand, after SMM approval */
    public function view(User $user, Content $content): bool
    {
        if ($user->role === Role::Client) {
            return $user->client_id === $content->task->item->calendar->client_id
                && $content->status->visibleToCustomer();
        }

        return $user->canSeeBrand($content->task->item->calendar->client_id);
    }

    /** Upload: the assigned Designer/Editor, or users with the upload-any permission */
    public function upload(User $user, Task $task): bool
    {
        return $task->assigned_to === $user->id || $user->hasPermission(Permission::UploadAnyContent);
    }

    public function resubmit(User $user, Content $content): bool
    {
        return $content->status === ContentStatus::RevisionRequired && $this->upload($user, $content->task);
    }

    /** First review: the chosen SMM (or Admin) */
    public function review(User $user, Content $content): bool
    {
        return $content->status->awaitingReview()
            && ($content->approver_id === $user->id || $user->isAdmin());
    }

    /** Final review: the brand's Customer */
    public function customerReview(User $user, Content $content): bool
    {
        return $content->status === ContentStatus::ClientReview
            && $user->role === Role::Client
            && $user->client_id === $content->task->item->calendar->client_id;
    }

    /** Swap wrong files of the current version while it still waits for the SMM's review */
    public function replace(User $user, Content $content): bool
    {
        return $content->status->awaitingReview() && $this->upload($user, $content->task);
    }

    /**
     * "Revision" on an approved / scheduled post (full round again: SMM, then Customer):
     * the brand's SMM (approve permission), Admin, or the brand's Customer. Not once it is live anywhere.
     */
    public function reopen(User $user, Content $content): bool
    {
        if (! $this->reopenable($content)) {
            return false;
        }
        if ($user->role === Role::Client) {
            return $user->client_id === $content->task->item->calendar->client_id;
        }

        return $user->hasPermission(Permission::ApproveContent) && $user->canSeeBrand($content->task->item->calendar->client_id);
    }

    /** "Send back for changes" (small fix like a missing logo or number): SMM / Admin; the SMM's approval of the fix is final */
    public function sendBack(User $user, Content $content): bool
    {
        return $this->reopenable($content) && $user->role !== Role::Client
            && $user->hasPermission(Permission::ApproveContent) && $user->canSeeBrand($content->task->item->calendar->client_id);
    }

    /** Approved or Scheduled, and nothing posted (or being posted) on Facebook / Instagram yet */
    private function reopenable(Content $content): bool
    {
        return in_array($content->status, [ContentStatus::Approved, ContentStatus::Scheduled], true)
            && ! \App\Models\MetaPost::where('content_id', $content->id)->whereIn('status', ['done', 'processing'])->exists();
    }

    /** Caption + hashtags belong to the SMM (approve or publish permission) */
    public function editCaption(User $user, Content $content): bool
    {
        return $user->hasPermission(Permission::ApproveContent) || $user->hasPermission(Permission::PublishContent);
    }

    public function comment(User $user, Content $content): bool
    {
        return $this->view($user, $content);
    }

    /** Approved => Scheduled => Published (publish permission) */
    public function markStatus(User $user, Content $content): bool
    {
        return $user->hasPermission(Permission::PublishContent)
            && in_array($content->status, [ContentStatus::Approved, ContentStatus::Scheduled], true);
    }

    /**
     * Publish to Facebook / Instagram. A Published post can still be sent when a platform failed,
     * or when it was only marked Published by hand (never sent: no Meta post at all).
     */
    public function publishMeta(User $user, Content $content): bool
    {
        if ($this->markStatus($user, $content)) {
            return true;
        }
        if ($content->status !== ContentStatus::Published || ! $user->hasPermission(Permission::PublishContent) || ! $this->view($user, $content)) {
            return false;
        }
        $sent = \App\Models\MetaPost::where('content_id', $content->id)->get();

        return $sent->isEmpty() || $sent->contains('status', 'failed');
    }
}
