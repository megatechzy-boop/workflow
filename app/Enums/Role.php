<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Approver = 'approver';
    case TeamMember = 'team_member';
    case Client = 'client';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Manager => 'Manager',
            self::Approver => 'Approver',
            self::TeamMember => 'Team Member',
            self::Client => 'Client',
        };
    }
}
