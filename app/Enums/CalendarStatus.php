<?php

namespace App\Enums;

enum CalendarStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';               // with the Admin/Ops Manager
    case ApproverApproved = 'approver_approved'; // with the Customer
    case ClientApproved = 'client_approved';     // Final: tasks get created

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Waiting for Ops Approval',
            self::ApproverApproved => 'Waiting for Customer',
            self::ClientApproved => 'Approved',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'bg-gray-100 text-gray-700',
            self::Submitted => 'bg-yellow-100 text-yellow-800',
            self::ApproverApproved => 'bg-blue-100 text-blue-800',
            self::ClientApproved => 'bg-green-100 text-green-800',
        };
    }
}
