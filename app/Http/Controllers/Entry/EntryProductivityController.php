<?php

namespace App\Http\Controllers\Entry;

use App\Http\Controllers\Controller;
use App\Models\EntryScan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class EntryProductivityController extends Controller
{
    public function index(Request $request): View
    {
        $tz = (string) config('app.timezone');
        $now = Carbon::now($tz);

        $filters = $request->validate([
            'period' => ['nullable', 'in:today,month,year'],
            'month' => ['nullable', 'date_format:Y-m'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $period = $filters['period'] ?? 'today';
        $monthValue = $filters['month'] ?? $now->format('Y-m');
        $yearValue = (int) ($filters['year'] ?? $now->year);

        [$periodStart, $periodEnd] = $this->periodBounds($period, $monthValue, $yearValue, $tz, $now);
        $periodDays = $this->periodDayCount($periodStart, $periodEnd, $now);

        $registeredQuery = $this->registeredScans()
            ->whereBetween('created_at', [$periodStart, $periodEnd]);

        $registeredInPeriod = (clone $registeredQuery)->count();

        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();
        $registeredToday = $this->registeredScans()
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->count();

        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $monthCount = $this->registeredScans()
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->count();
        $daysElapsedInMonth = max(1, $monthStart->diffInDays($now->copy()->startOfDay()) + 1);
        $avgPerDay = round($monthCount / $daysElapsedInMonth, 1);

        $yearStart = $now->copy()->startOfYear();
        $yearCount = $this->registeredScans()
            ->whereBetween('created_at', [$yearStart, $now->copy()->endOfDay()])
            ->count();
        $monthsElapsed = max(1, (int) $now->month);
        $avgPerMonth = round($yearCount / $monthsElapsed, 1);

        $pendingShelf = (clone $registeredQuery)
            ->where('status', EntryScan::STATUS_SAVED)
            ->count();
        $onShelf = (clone $registeredQuery)
            ->where('status', EntryScan::STATUS_STORED)
            ->count();
        $needsCorrection = (clone $registeredQuery)
            ->where('review_status', EntryScan::REVIEW_NEEDS_CORRECTION)
            ->count();
        $verified = (clone $registeredQuery)
            ->where('review_status', EntryScan::REVIEW_VERIFIED)
            ->count();
        $pendingReview = (clone $registeredQuery)
            ->where(function ($query): void {
                $query->whereNull('review_status')
                    ->orWhere('review_status', EntryScan::REVIEW_PENDING);
            })
            ->count();

        $operatorTimestamps = (clone $registeredQuery)
            ->orderBy('created_at')
            ->get(['user_id', 'created_at'])
            ->groupBy('user_id');

        $users = User::query()
            ->whereIn('id', $operatorTimestamps->keys()->all())
            ->get(['id', 'name', 'email'])
            ->keyBy('id');

        $operators = $operatorTimestamps
            ->map(function (Collection $rows, $userId) use ($users, $periodDays) {
                $times = $rows->pluck('created_at')->sort()->values();
                $user = $users->get((int) $userId);
                $count = $times->count();
                [$perMinute, $perHour] = $this->throughputRates($times);

                return [
                    'user_id' => (int) $userId,
                    'name' => $user?->name ?? 'Tidak diketahui',
                    'email' => $user?->email,
                    'count' => $count,
                    'files_per_minute' => $perMinute,
                    'files_per_hour' => $perHour,
                    'files_per_day' => round($count / $periodDays, 1),
                ];
            })
            ->sortByDesc('count')
            ->values();

        $periodLabel = match ($period) {
            'month' => Carbon::createFromFormat('Y-m', $monthValue, $tz)->translatedFormat('F Y'),
            'year' => (string) $yearValue,
            default => 'Hari ini',
        };

        return view('entry.productivity.index', [
            'period' => $period,
            'monthValue' => $monthValue,
            'yearValue' => $yearValue,
            'periodLabel' => $periodLabel,
            'registeredToday' => $registeredToday,
            'registeredInPeriod' => $registeredInPeriod,
            'avgPerDay' => $avgPerDay,
            'avgPerMonth' => $avgPerMonth,
            'monthCount' => $monthCount,
            'yearCount' => $yearCount,
            'pendingShelf' => $pendingShelf,
            'onShelf' => $onShelf,
            'needsCorrection' => $needsCorrection,
            'verified' => $verified,
            'pendingReview' => $pendingReview,
            'operators' => $operators,
        ]);
    }

    private function registeredScans()
    {
        return EntryScan::query()
            ->whereIn('status', [EntryScan::STATUS_SAVED, EntryScan::STATUS_STORED]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function periodBounds(string $period, string $monthValue, int $yearValue, string $tz, Carbon $now): array
    {
        if ($period === 'month') {
            $start = Carbon::createFromFormat('Y-m', $monthValue, $tz)->startOfMonth();

            return [$start, $start->copy()->endOfMonth()];
        }

        if ($period === 'year') {
            $start = Carbon::create($yearValue, 1, 1, 0, 0, 0, $tz)->startOfYear();
            $end = $start->copy()->endOfYear();
            if ($yearValue === (int) $now->year && $end->gt($now)) {
                $end = $now->copy()->endOfDay();
            }

            return [$start, $end];
        }

        $today = $now->copy();

        return [$today->copy()->startOfDay(), $today->copy()->endOfDay()];
    }

    private function periodDayCount(Carbon $periodStart, Carbon $periodEnd, Carbon $now): int
    {
        $endDay = $periodEnd->lt($now) ? $periodEnd->copy()->startOfDay() : $now->copy()->startOfDay();

        return max(1, $periodStart->copy()->startOfDay()->diffInDays($endDay) + 1);
    }

    /**
     * Wall-clock throughput from first to last file (breaks included).
     *
     * @param  Collection<int, Carbon>  $times
     * @return array{0: ?float, 1: ?float} [files_per_minute, files_per_hour]
     */
    private function throughputRates(Collection $times): array
    {
        if ($times->count() < 2) {
            return [null, null];
        }

        $elapsedSeconds = $times->first()->diffInSeconds($times->last());
        if ($elapsedSeconds <= 0) {
            return [null, null];
        }

        $count = $times->count();

        return [
            round($count / ($elapsedSeconds / 60), 2),
            round($count / ($elapsedSeconds / 3600), 1),
        ];
    }
}
