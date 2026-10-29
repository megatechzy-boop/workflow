<?php

namespace App\Enums;

/** Granted per role (Admin > Roles) and per member (Users > Edit). Admin always has all. */
enum Permission: string
{
    case ManageUsers = 'manage_users';
    case ManageClients = 'manage_clients';
    case ManageCalendars = 'manage_calendars';
    case ApproveCalendar = 'approve_calendar';
    case AssignTasks = 'assign_tasks';
    case ApproveContent = 'approve_content';
    case PublishContent = 'publish_content';
    case ViewAllTasks = 'view_all_tasks';
    case UploadAnyContent = 'upload_any_content';
    case ViewPrices = 'view_prices';

    public function label(): string
    {
        return match ($this) {
            self::ManageUsers => 'Manage users',
            self::ManageClients => 'Manage clients (brands)',
            self::ManageCalendars => 'Create / edit / submit content calendars',
            self::ApproveCalendar => 'Approve calendars (before the Customer)',
            self::AssignTasks => 'Assign tasks',
            self::ApproveContent => 'Approve content (appear in the Approver list)',
            self::PublishContent => 'Mark content Scheduled / Published',
            self::ViewAllTasks => 'View all tasks, approvals and points',
            self::UploadAnyContent => 'Upload content on any task',
            self::ViewPrices => 'See package prices',
        };
    }

    /** Initial defaults (same as the previously hard-coded behaviour) */
    public static function defaults(): array
    {
        return [
            Role::Manager->value => [self::ManageClients, self::ManageCalendars, self::ApproveCalendar, self::AssignTasks, self::PublishContent, self::ViewAllTasks, self::UploadAnyContent, self::ViewPrices],
            Role::SocialMediaManager->value => [self::ManageCalendars, self::AssignTasks, self::ApproveContent, self::PublishContent, self::ViewAllTasks],
            Role::GraphicDesigner->value => [],
            Role::VideoEditor->value => [],
        ];
    }
}
