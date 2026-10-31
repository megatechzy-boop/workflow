<?php

namespace App\Support;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\ChatMessage;
use App\Models\Lead;
use App\Models\MetaComment;
use App\Models\MetaLead;
use App\Models\User;

/** One menu definition for the desktop sidebar, the phone tab bar and the phone "More" sheet */
class Navigation
{
    /** @return array<int, array{label: string, route: string, icon: string, active: string|string[], group: string, badge?: int}> */
    public static function items(User $user): array
    {
        $isCustomer = $user->role === Role::Client;
        $seesTasks = $user->role->isCreator() || $user->hasPermission(Permission::ViewAllTasks);
        $seesApprovals = $isCustomer || $user->hasPermission(Permission::ApproveContent) || $user->hasPermission(Permission::ViewAllTasks);
        $publishes = $user->hasPermission(Permission::PublishContent);
        $items = [];
        $add = function (string $group, string $label, string $route, string $icon, string|array $active, ?int $badge = null) use (&$items) {
            $items[] = ['label' => $label, 'route' => $route, 'icon' => $icon, 'active' => $active, 'group' => $group] + ($badge === null ? [] : ['badge' => $badge]);
        };

        $add('', 'Home', 'dashboard', 'home', 'dashboard');
        $add('', 'Chat', 'chat.index', 'chat', 'chat.*', array_sum(ChatMessage::unreadCounts($user)));

        $add('Content', 'Calendars', 'calendars.index', 'calendar', 'calendars.*');
        if ($seesTasks) {
            $add('Content', $user->hasPermission(Permission::ViewAllTasks) ? 'Tasks' : 'My Tasks', 'tasks.index', 'check', 'tasks.*');
        }
        if ($seesApprovals) {
            $add('Content', 'Approvals', 'contents.index', 'inbox', 'contents.*');
        }
        $add('Content', 'Schedule', 'schedule', 'grid', 'schedule');
        $add('Content', 'Assets', 'assets.index', 'photo', 'assets.*');

        if ($publishes) {
            $add('Social media', 'Comments', 'meta.comments', 'chat', 'meta.comments*',
                MetaComment::whereNull('replied_at')->when($user->brandLimit(), fn ($q, $ids) => $q->whereIn('client_id', $ids))->count());
        }
        if ($isCustomer || $publishes || $user->hasPermission(Permission::ManageClients)) {
            $add('Social media', 'Meta leads', 'meta.leads', 'users', 'meta.leads*', MetaLead::where('status', 'new')
                ->when($isCustomer, fn ($q) => $q->where('client_id', $user->client_id ?? 0))
                ->when($user->brandLimit(), fn ($q, $ids) => $q->whereIn('client_id', $ids))->count());
        }
        if ($publishes) {
            $add('Meta ads', 'Ads', 'ads.index', 'play', ['ads.index', 'ads.create']);
            $add('Meta ads', 'Audiences', 'ads.audiences', 'users', 'ads.audiences*');
            $add('Meta ads', 'Pixel', 'ads.pixel', 'search', 'ads.pixel*');
        }

        if ($user->hasPermission(Permission::ManageClients)) {
            $add('Clients', 'Clients', 'clients.index', 'building', 'clients.*');
            $add('Clients', 'Leads', 'leads.index', 'inbox', 'leads.*', Lead::where('status', 'new')->count());
        }
        if ($isCustomer || $user->hasPermission(Permission::ViewAllTasks)) {
            $add($isCustomer ? '' : 'Clients', 'Reports', 'reports.monthly', 'report', 'reports.*');
        }

        if (! $isCustomer) {
            $add('Team', 'Leaderboard', 'leaderboard', 'trophy', 'leaderboard');
        }
        if ($user->isAdmin()) {
            $add('Team', 'Attendance', 'admin.attendance.index', 'calendar', 'admin.attendance.*');
        }
        if ($user->hasPermission(Permission::ManageUsers)) {
            $add('Team', 'Users', 'admin.users.index', 'users', 'admin.users.*');
        }
        if ($user->isAdmin()) {
            $add('Team', 'Roles', 'admin.roles.index', 'shield', 'admin.roles.*');
            $add('Accounts', 'Billing', 'admin.billing.index', 'report', 'admin.billing.*');
            $add('Accounts', 'Packages', 'admin.packages.index', 'tag', 'admin.packages.*');
            $add('Accounts', 'Salary', 'admin.salary.index', 'users', 'admin.salary.*');
            $add('Accounts', 'Expenses', 'admin.expenses.index', 'clipboard', 'admin.expenses.*');
            $add('Settings', 'Facebook / IG', 'admin.meta.index', 'send', 'admin.meta.*');
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
