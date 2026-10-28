<?php

namespace App\Support;

use App\Models\CalendarItem;
use Illuminate\Support\Carbon;

/**
 * Rows pasted from Excel / Google Sheets (tab separated, quoted multi-line cells allowed).
 * Columns: [Sr. No.] | Date | Post type | Creative copy | Visual brief | Brief | Reference link | Remarks
 * Post type examples: "LinkedIn Post", "Instagram, Facebook Reel", "IG Carousel".
 */
class SheetImport
{
    private const PLATFORMS = ['instagram' => ['instagram', 'insta', 'ig'], 'facebook' => ['facebook', 'fb'], 'linkedin' => ['linkedin', 'li'], 'youtube' => ['youtube', 'yt']];

    private const FORMATS = ['reel' => ['reel', 'reels', 'video', 'short', 'shorts'], 'carousel' => ['carousel', 'slides'], 'post' => ['post', 'static', 'image', 'single']];

    /** @return array{0: array<int, array>, 1: array<int, string>} [rows to create, errors] */
    public static function parse(string $text, Carbon $month): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, str_replace("\r\n", "\n", $text));
        rewind($stream);

        $rows = [];
        $errors = [];
        $line = 0;
        while (($cells = fgetcsv($stream, 0, "\t", '"', '')) !== false) {
            $line++;
            $cells = array_map(fn ($c) => trim((string) $c), $cells);
            if (! array_filter($cells)) {
                continue; // empty line
            }
            if (preg_match('/\b(date|sr\.?\s*no)\b/i', implode(' ', array_slice($cells, 0, 2)))) {
                continue; // header row
            }
            // Leading "Sr. No." column (a small number followed by a date cell) is dropped.
            // Cell counts are unreliable: trailing empty cells get trimmed on submit.
            if (preg_match('/^\d{1,3}$/', $cells[0]) && preg_match('/\d/', $cells[1] ?? '')) {
                array_shift($cells);
            }
            $cells = array_pad($cells, 7, '');
            [$date, $type, $copy, $visual, $brief, $link, $remarks] = $cells;

            $postDate = self::date($date, $month);
            if (! $postDate) {
                $errors[] = "Row $line: date \"$date\" is not a day in ".$month->format('F Y').'.';

                continue;
            }

            [$postTypes, $typeError] = self::postTypes($type);
            if ($typeError) {
                $errors[] = "Row $line: $typeError";

                continue;
            }

            // A non-URL reference goes into remarks instead of failing the row
            if ($link !== '' && ! filter_var($link, FILTER_VALIDATE_URL)) {
                $remarks = trim($remarks."\nReference: ".$link);
                $link = '';
            }

            $rows[] = [
                'post_date' => $postDate->toDateString(),
                'post_types' => $postTypes,
                'creative_copy' => $copy ?: null,
                'visual_brief' => $visual ?: null,
                'brief' => $brief ?: null,
                'reference_link' => $link ?: null,
                'remarks' => $remarks ?: null,
            ];
        }
        fclose($stream);

        return [$rows, $errors];
    }

    /** "11 Oct Sunday", "11/10/2026", "2026-10-11" or "11" => that day of the calendar month */
    private static function date(string $value, Carbon $month): ?Carbon
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $m)) {
            $d = Carbon::create((int) $m[1], (int) $m[2], (int) $m[3]);

            return $d->isSameMonth($month) ? $d : null;
        }
        if (! preg_match('/\d{1,2}/', $value, $m)) {
            return null;
        }
        $day = (int) $m[0];

        return $day >= 1 && $day <= $month->daysInMonth ? $month->copy()->day($day) : null;
    }

    /** @return array{0: array<int, string>, 1: ?string} */
    private static function postTypes(string $value): array
    {
        $words = preg_split('/[^a-z]+/', strtolower($value), -1, PREG_SPLIT_NO_EMPTY);
        $formats = array_keys(array_filter(self::FORMATS, fn ($aliases) => array_intersect($aliases, $words)));
        $platforms = array_keys(array_filter(self::PLATFORMS, fn ($aliases) => array_intersect($aliases, $words)));

        if (count($formats) !== 1) {
            return [[], $formats ? "\"$value\" has more than one format; use one row per format." : "post type \"$value\" needs Post, Carousel or Reel."];
        }
        if (! $platforms) {
            return [[], "post type \"$value\" needs a platform (Instagram, Facebook, LinkedIn or YouTube)."];
        }

        $keys = array_map(fn ($p) => $p.'_'.$formats[0], $platforms);
        $invalid = array_diff($keys, array_keys(CalendarItem::typeOptions()));

        return $invalid ? [[], 'YouTube has no Carousel.'] : [$keys, null];
    }
}
