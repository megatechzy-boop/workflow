<?php

namespace App\Policies;

use App\Enums\CalendarStatus;
use App\Enums\Role;
use App\Models\Calendar;
use App\Models\User;

class CalendarPolicy
{
    /** Calendar banane/edit karne wale roles */
    private function isStaff(User $user): bool
    {
        return $user->hasRole('admin', 'manager', 'team_member');
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Calendar $calendar): bool
    {
        if ($user->role === Role::Client) {
            // Client ko sirf apna calendar, aur woh bhi Approver ke OK ke baad
            return $user->client_id === $calendar->client_id
                && in_array($calendar->status, [CalendarStatus::ApproverApproved, CalendarStatus::ClientApproved], true);
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $this->isStaff($user);
    }

    public function update(User $user, Calendar $calendar): bool
    {
        return $this->isStaff($user) && $calendar->status === CalendarStatus::Draft;
    }

    public function delete(User $user, Calendar $calendar): bool
    {
        return $user->hasRole('admin', 'manager') && $calendar->status === CalendarStatus::Draft;
    }

    public function submit(User $user, Calendar $calendar): bool
    {
        return $this->update($user, $calendar) && $calendar->items()->exists();
    }

    /** Approver ya Client ka review: jiska turn hai wahi approve/revision kar sakta hai */
    public function review(User $user, Calendar $calendar): bool
    {
        return match ($calendar->status) {
            CalendarStatus::Submitted => $user->hasRole('admin', 'approver'),
            CalendarStatus::ApproverApproved => $user->role === Role::Client && $user->client_id === $calendar->client_id,
            default => false,
        };
    }
}
