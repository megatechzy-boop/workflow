<?php

namespace App\Support;

/** 108000 → "One Lakh Eight Thousand Rupees Only" (Indian numbering) */
class RupeeWords
{
    private const ONES = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve',
        'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];

    private const TENS = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    public static function of(int $amount): string
    {
        return ($amount > 0 ? self::words($amount) : 'Zero').' Rupees Only';
    }

    private static function words(int $n): string
    {
        $parts = [];
        if ($n >= 10000000) {
            $parts[] = self::words(intdiv($n, 10000000)).' Crore';
            $n %= 10000000;
        }
        foreach ([[100000, 'Lakh'], [1000, 'Thousand'], [100, 'Hundred']] as [$size, $name]) {
            if ($n >= $size) {
                $parts[] = self::small(intdiv($n, $size)).' '.$name;
                $n %= $size;
            }
        }
        if ($n > 0) {
            $parts[] = self::small($n);
        }

        return implode(' ', $parts);
    }

    private static function small(int $n): string
    {
        return $n < 20 ? self::ONES[$n] : trim(self::TENS[intdiv($n, 10)].' '.self::ONES[$n % 10]);
    }
}
