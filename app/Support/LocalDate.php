<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class LocalDate
{
    public static function timezone(): string
    {
        return (string) config('app.display_timezone', 'Asia/Kuala_Lumpur');
    }

    public static function format(CarbonInterface|string|null $value, string $format = 'd M Y, H:i'): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        // DB datetimes are UTC wall-clock strings; ignore app timezone when parsing.
        $utcWallClock = $value instanceof CarbonInterface
            ? $value->format('Y-m-d H:i:s')
            : (string) $value;

        return Carbon::parse($utcWallClock, 'UTC')
            ->timezone(self::timezone())
            ->format($format);
    }

    /** Calendar dates (no timezone shift) as dd/mm/yyyy. */
    public static function formatDate(CarbonInterface|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if ($value instanceof CarbonInterface) {
            return $value->format('d/m/Y');
        }

        $raw = trim((string) $value);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $parts) === 1) {
            return $parts[3].'/'.$parts[2].'/'.$parts[1];
        }
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $raw) === 1) {
            return $raw;
        }

        return Carbon::parse($raw)->format('d/m/Y');
    }
}
