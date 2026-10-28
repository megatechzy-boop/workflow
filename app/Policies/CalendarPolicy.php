<?php

namespace App\Policies;

use App\Enums\CalendarStatus;
use App\Enums\Permission;
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
            // Customers only see their own calendar, and only after submission
            return $user->client_id === $calendar->client_id
                && in_array($calendar->status, [CalendarStatus::ApproverApproved, CalendarStatus::ClientApproved], true);
        }

        return true;
    }

    /** Users with the calendar permission (default: SMM, Ops Manager) */
    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ManageCalendars);
    }

    public function update(User $user, Calendar $calendar): bool
    {
        return $user->hasPermission(Permission::ManageCalendars) && $calendar->status === CalendarStatus::Draft;
    }

    public function delete(User $user, Calendar $calendar): bool
    {
        return $this->update($user, $calendar);
    }

    public function submit(User $user, Calendar $calendar): bool
    {
        return $this->update($user, $calendar) && $calendar->items()->exists();
    }

    /** Assign Designers/Editors to the posts of a Customer-approved calendar */
    public function assign(User $user, Calendar $calendar): bool
    {
        return $user->hasPermission(Permission::AssignTasks) && $calendar->status === CalendarStatus::ClientApproved;
    }

    /** Ops step: the whole calendar (approve calendar permission) */
    public function review(User $user, Calendar $calendar): bool
    {
        return $calendar->status === CalendarStatus::Submitted && $user->hasPermission(Permission::ApproveCalendar);
    }

    /** Customer step: the brand's Customer checks every row separately */
    public function reviewItems(User $user, Calendar $calendar): bool
    {
        return $calendar->status === CalendarStatus::ApproverApproved
            && $user->role === Role::Client && $user->client_id === $calendar->client_id;
    }
}
