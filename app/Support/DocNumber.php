<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/** Next "A00324"-style number for invoices or receipts, continuing the old series */
class DocNumber
{
    public static function next(string $table, int $start): string
    {
        $prefix = config('business.invoice_prefix');
        $last = DB::table($table)->where('number', 'like', $prefix.'%')->pluck('number')
            ->map(fn ($n) => (int) substr($n, strlen($prefix)))->max() ?? 0;

        return $prefix.str_pad(max($start, $last + 1), 5, '0', STR_PAD_LEFT);
    }
}
