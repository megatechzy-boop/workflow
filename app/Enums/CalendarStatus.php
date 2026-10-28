<?php

namespace App\Enums;

enum CalendarStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';               // purana internal step (ab use nahi hota)
    case ApproverApproved = 'approver_approved'; // Customer ke paas
    case ClientApproved = 'client_approved';     // Final: ab tasks banenge

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Waiting for Review',
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
