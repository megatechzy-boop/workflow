<?php

namespace App\Policies;

use App\Enums\CalendarStatus;
use App\Enums\Role;
use App\Models\Calendar;
use App\Models\User;

class CalendarPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Calendar $calendar): bool
    {
        if ($user->role === Role::Client) {
            // Customer ko sirf apna calendar, aur woh bhi submit hone ke baad
            return $user->client_id === $calendar->client_id
                && in_array($calendar->status, [CalendarStatus::ApproverApproved, CalendarStatus::ClientApproved], true);
        }

        return true;
    }

    /** Calendar SMM (ya Admin/Ops Manager) banata hai */
    public function create(User $user): bool
    {
        return $user->role->isPlanner();
    }

    public function update(User $user, Calendar $calendar): bool
    {
        return $user->role->isPlanner() && $calendar->status === CalendarStatus::Draft;
    }

    public function delete(User $user, Calendar $calendar): bool
    {
        return $this->update($user, $calendar);
    }

    public function submit(User $user, Calendar $calendar): bool
    {
        return $this->update($user, $calendar) && $calendar->items()->exists();
    }

    /** Customer-approved calendar ke posts par Designer/Editor assign karna */
    public function assign(User $user, Calendar $calendar): bool
    {
        return $user->role->isPlanner() && $calendar->status === CalendarStatus::ClientApproved;
    }

    /** Customer review karta hai; purane "Submitted" calendars Admin/Ops/SMM aage badha sakte hain */
    public function review(User $user, Calendar $calendar): bool
    {
        return match ($calendar->status) {
            CalendarStatus::Submitted => $user->role->isPlanner(),
            CalendarStatus::ApproverApproved => $user->role === Role::Client && $user->client_id === $calendar->client_id,
            default => false,
        };
    }
}
