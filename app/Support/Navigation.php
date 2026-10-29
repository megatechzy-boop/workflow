<?php

namespace App\Support;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\ChatMessage;
use App\Models\Lead;
use App\Models\User;

/** One menu definition for the desktop sidebar, the phone tab bar and the phone "More" sheet */
class Navigation
{
    /** @return array<int, array{label: string, route: string, icon: string, active: string, badge?: int}> */
    public static function items(User $user): array
    {
        $isCustomer = $user->role === Role::Client;
        $seesTasks = $user->role->isCreator() || $user->hasPermission(Permission::ViewAllTasks);
        $seesApprovals = $isCustomer || $user->hasPermission(Permission::ApproveContent) || $user->hasPermission(Permission::ViewAllTasks);

        $items = [
            ['label' => 'Home', 'route' => 'dashboard', 'icon' => 'home', 'active' => 'dashboard'],
            ['label' => 'Calendars', 'route' => 'calendars.index', 'icon' => 'calendar', 'active' => 'calendars.*'],
            ['label' => 'Schedule', 'route' => 'schedule', 'icon' => 'grid', 'active' => 'schedule'],
        ];
        if ($seesTasks) {
            $items[] = ['label' => $user->hasPermission(Permission::ViewAllTasks) ? 'Tasks' : 'My Tasks', 'route' => 'tasks.index', 'icon' => 'check', 'active' => 'tasks.*'];
        }
        if ($seesApprovals) {
            $items[] = ['label' => 'Approvals', 'route' => 'contents.index', 'icon' => 'inbox', 'active' => 'contents.*'];
        }
        $items[] = ['label' => 'Chat', 'route' => 'chat.index', 'icon' => 'chat', 'active' => 'chat.*', 'badge' => array_sum(ChatMessage::unreadCounts($user))];
        $items[] = ['label' => 'Assets', 'route' => 'assets.index', 'icon' => 'photo', 'active' => 'assets.*'];
        if ($isCustomer || $user->hasPermission(Permission::ViewAllTasks)) {
            $items[] = ['label' => 'Reports', 'route' => 'reports.monthly', 'icon' => 'report', 'active' => 'reports.*'];
        }
        if (! $isCustomer) {
            $items[] = ['label' => 'Leaderboard', 'route' => 'leaderboard', 'icon' => 'trophy', 'active' => 'leaderboard'];
        }
        if ($user->hasPermission(Permission::ManageClients)) {
            $items[] = ['label' => 'Clients', 'route' => 'clients.index', 'icon' => 'building', 'active' => 'clients.*'];
            $items[] = ['label' => 'Leads', 'route' => 'leads.index', 'icon' => 'inbox', 'active' => 'leads.*', 'badge' => Lead::where('status', 'new')->count()];
        }
        if ($user->hasPermission(Permission::ManageUsers)) {
            $items[] = ['label' => 'Users', 'route' => 'admin.users.index', 'icon' => 'users', 'active' => 'admin.users.*'];
        }
        if ($user->isAdmin()) {
            $items[] = ['label' => 'Roles', 'route' => 'admin.roles.index', 'icon' => 'shield', 'active' => 'admin.roles.*'];
            $items[] = ['label' => 'Packages', 'route' => 'admin.packages.index', 'icon' => 'tag', 'active' => 'admin.packages.*'];
            $items[] = ['label' => 'Billing', 'route' => 'admin.billing.index', 'icon' => 'report', 'active' => 'admin.billing.*'];
            $items[] = ['label' => 'Attendance', 'route' => 'admin.attendance.index', 'icon' => 'calendar', 'active' => 'admin.attendance.*'];
            $items[] = ['label' => 'Salary', 'route' => 'admin.salary.index', 'icon' => 'users', 'active' => 'admin.salary.*'];
            $items[] = ['label' => 'Expenses', 'route' => 'admin.expenses.index', 'icon' => 'clipboard', 'active' => 'admin.expenses.*'];
        }

        return $items;
    }

    /** Phone tab bar: Home + the 3 most used for this role (the rest live under "More") */
    public static function primary(User $user): array
    {
        $items = collect(self::items($user))->keyBy('route');
        $work = $user->role->isCreator() ? 'tasks.index' : 'contents.index';
        $second = $user->role === Role::Client || $user->role->isCreator() ? 'calendars.index' : 'schedule';

        return $items->only(['dashboard', $second, $work, 'chat.index'])
            ->sortBy(fn ($i, $route) => array_search($route, ['dashboard', $second, $work, 'chat.index'], true))
            ->values()->all();
    }
}
