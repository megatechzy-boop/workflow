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
    /** Agency team sab dekh sakti hai; Customer sirf apne brand ka, SMM ke OK ke baad */
    public function view(User $user, Content $content): bool
    {
        if ($user->role === Role::Client) {
            return $user->client_id === $content->task->item->calendar->client_id
                && $content->status->visibleToCustomer();
        }

        return true;
    }

    /** Upload: assigned Designer/Editor, ya "kisi bhi task par upload" permission wale */
    public function upload(User $user, Task $task): bool
    {
        return $task->assigned_to === $user->id || $user->hasPermission(Permission::UploadAnyContent);
    }

    public function resubmit(User $user, Content $content): bool
    {
        return $content->status === ContentStatus::RevisionRequired && $this->upload($user, $content->task);
    }

    /** Pehla review: chuna hua SMM (ya Admin) */
    public function review(User $user, Content $content): bool
    {
        return $content->status->awaitingReview()
            && ($content->approver_id === $user->id || $user->isAdmin());
    }

    /** Final review: apne brand ka Customer */
    public function customerReview(User $user, Content $content): bool
    {
        return $content->status === ContentStatus::ClientReview
            && $user->role === Role::Client
            && $user->client_id === $content->task->item->calendar->client_id;
    }

    public function comment(User $user, Content $content): bool
    {
        return $this->view($user, $content);
    }

    /** Approved => Scheduled => Published ("publish" permission wale) */
    public function markStatus(User $user, Content $content): bool
    {
        return $user->hasPermission(Permission::PublishContent)
            && in_array($content->status, [ContentStatus::Approved, ContentStatus::Scheduled], true);
    }
}
