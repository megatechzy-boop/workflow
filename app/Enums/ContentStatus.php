<?php

namespace App\Enums;

enum ContentStatus: string
{
    case Submitted = 'submitted';
    case RevisionRequired = 'revision_required';
    case Resubmitted = 'resubmitted';
    case ClientReview = 'client_review'; // SMM ne OK kiya, ab Customer ke paas
    case Approved = 'approved';          // Customer ne final OK kiya
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::RevisionRequired => 'Revision Required',
            self::Resubmitted => 'Resubmitted',
            self::ClientReview => 'Waiting for Customer',
            self::Approved => 'Approved',
            self::Scheduled => 'Scheduled',
            self::Published => 'Published',
            self::Rejected => 'Rejected',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Submitted, self::Resubmitted => 'bg-yellow-100 text-yellow-800',
            self::RevisionRequired => 'bg-orange-100 text-orange-800',
            self::ClientReview => 'bg-purple-100 text-purple-800',
            self::Approved => 'bg-green-100 text-green-800',
            self::Scheduled => 'bg-blue-100 text-blue-800',
            self::Published => 'bg-indigo-100 text-indigo-800',
            self::Rejected => 'bg-red-100 text-red-800',
        };
    }

    /** Customer ko dikhne wale (SMM ke OK ke baad) */
    public function visibleToCustomer(): bool
    {
        return in_array($this, [self::ClientReview, self::Approved, self::Scheduled, self::Published], true);
    }

    /** Approver ke review ka intezaar */
    public function awaitingReview(): bool
    {
        return in_array($this, [self::Submitted, self::Resubmitted], true);
    }
}
