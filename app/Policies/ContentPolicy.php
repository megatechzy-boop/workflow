<?php

namespace App\Policies;

use App\Enums\ContentStatus;
use App\Enums\Role;
use App\Models\Content;
use App\Models\Task;
use App\Models\User;

class ContentPolicy
{
    /** Agency team dekh sakti hai; Client ko abhi content access nahi */
    public function view(User $user, Content $content): bool
    {
        return $user->role !== Role::Client;
    }

    /** Task par pehli baar upload: assigned member ya Admin/Manager */
    public function upload(User $user, Task $task): bool
    {
        return $task->assigned_to === $user->id || $user->hasRole('admin', 'manager');
    }

    public function resubmit(User $user, Content $content): bool
    {
        return $content->status === ContentStatus::RevisionRequired && $this->upload($user, $content->task);
    }

    /** Sirf chuna hua Approver (ya Admin), aur tab jab review pending ho */
    public function review(User $user, Content $content): bool
    {
        return $content->status->awaitingReview()
            && ($content->approver_id === $user->id || $user->isAdmin());
    }

    public function comment(User $user, Content $content): bool
    {
        return $this->view($user, $content);
    }

    /** Approved => Scheduled => Published (manual) */
    public function markStatus(User $user, Content $content): bool
    {
        return $user->hasRole('admin', 'manager')
            && in_array($content->status, [ContentStatus::Approved, ContentStatus::Scheduled], true);
    }
}
