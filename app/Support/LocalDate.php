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
}
