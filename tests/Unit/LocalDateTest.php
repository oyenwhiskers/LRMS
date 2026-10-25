<?php

namespace Tests\Unit;

use App\Support\LocalDate;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LocalDateTest extends TestCase
{
    public function test_formats_utc_database_time_in_malaysia_display_timezone(): void
    {
        config([
            'app.timezone' => 'UTC',
            'app.display_timezone' => 'Asia/Kuala_Lumpur',
        ]);

        $utc = Carbon::parse('2026-10-23 00:57:05', 'UTC');

        $this->assertSame('23 Oct 2026, 08:57', LocalDate::format($utc));
        $this->assertSame('2026-10-23 08:57', LocalDate::format($utc, 'Y-m-d H:i'));
    }

    public function test_formats_correctly_even_when_app_timezone_is_kuala_lumpur(): void
    {
        config([
            'app.timezone' => 'Asia/Kuala_Lumpur',
            'app.display_timezone' => 'Asia/Kuala_Lumpur',
        ]);

        // Same UTC wall-clock string Laravel would load from MySQL.
        $misreadAsLocal = Carbon::parse('2026-10-23 00:57:05', 'Asia/Kuala_Lumpur');

        $this->assertSame('23 Oct 2026, 08:57', LocalDate::format($misreadAsLocal));
    }

    public function test_format_date_uses_dd_mm_yyyy_without_timezone_shift(): void
    {
        $this->assertSame('29/09/2025', LocalDate::formatDate('2025-09-29'));
        $this->assertSame('01/01/2026', LocalDate::formatDate(Carbon::parse('2026-01-01')));
        $this->assertSame('15/03/2024', LocalDate::formatDate('15/03/2024'));
        $this->assertSame('—', LocalDate::formatDate(null));
    }
}
