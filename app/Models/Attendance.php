<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One row per team member per day: present (checked in), half_day or leave */
class Attendance extends Model
{
    public const STATUSES = ['present' => 'Present', 'half_day' => 'Half day', 'leave' => 'Leave'];

    protected $fillable = ['user_id', 'date', 'status', 'check_in_at', 'check_out_at'];

    protected function casts(): array
    {
        return ['date' => 'date', 'check_in_at' => 'datetime', 'check_out_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Working days in a month: every day except Sunday */
    public static function workingDays(CarbonImmutable $month): int
    {
        $days = 0;
        for ($d = $month->startOfMonth(); $d->lte($month->endOfMonth()); $d = $d->addDay()) {
            $days += $d->isSunday() ? 0 : 1;
        }

        return $days;
    }

    /** Paid days per user for a month: present = 1, half day = 0.5, leave / no entry = 0 */
    public static function paidDays(CarbonImmutable $month): array
    {
        return self::whereBetween('date', [$month->startOfMonth(), $month->endOfMonth()])
            ->get(['user_id', 'status'])
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->sum(fn ($r) => ['present' => 1, 'half_day' => 0.5][$r->status] ?? 0))
            ->all();
    }
}
