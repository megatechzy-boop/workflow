<?php

namespace App\Enums;

/** Admin > Roles se role-wise, aur Users > Edit se member-wise di jaati hain. Admin ke paas hamesha sab. */
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

    public function label(): string
    {
        return match ($this) {
            self::ManageUsers => 'Users manage karna',
            self::ManageClients => 'Clients (brands) manage karna',
            self::ManageCalendars => 'Content calendar banana / edit / submit',
            self::ApproveCalendar => 'Calendar approve karna (Customer se pehle)',
            self::AssignTasks => 'Tasks assign karna',
            self::ApproveContent => 'Content approve karna (Approver list mein aana)',
            self::PublishContent => 'Scheduled / Published mark karna',
            self::ViewAllTasks => 'Sabke tasks, approvals aur points dekhna',
            self::UploadAnyContent => 'Kisi bhi task par content upload karna',
        };
    }

    /** Pehli baar ke defaults (wahi jo pehle code mein fixed tha) */
    public static function defaults(): array
    {
        return [
            Role::Manager->value => [self::ManageClients, self::ManageCalendars, self::ApproveCalendar, self::AssignTasks, self::PublishContent, self::ViewAllTasks, self::UploadAnyContent],
            Role::SocialMediaManager->value => [self::ManageCalendars, self::AssignTasks, self::ApproveContent, self::PublishContent, self::ViewAllTasks],
            Role::GraphicDesigner->value => [],
            Role::VideoEditor->value => [],
        ];
    }
}
