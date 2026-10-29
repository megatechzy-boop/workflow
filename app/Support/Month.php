<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/** ?month=2026-10 → first day of that month (defaults to this month) */
class Month
{
    public static function from(Request $request): CarbonImmutable
    {
        $value = (string) $request->query('month');

        return preg_match('/^\d{4}-\d{2}$/', $value)
            ? CarbonImmutable::createFromFormat('!Y-m', $value)
            : CarbonImmutable::now()->startOfMonth();
    }
}
