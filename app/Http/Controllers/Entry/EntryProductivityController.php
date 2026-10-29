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
    /** Gaps longer than this are treated as a break, not work speed. */
    private const SPEED_GAP_CAP_SECONDS = 7200;

    public function index(Request $request): View
    {
        $tz = (string) config('app.timezone');
        $now = Carbon::now($tz);

        $filters = $request->validate([
            'period' => ['nullable', 'in:today,month,date'],
            'month' => ['nullable', 'date_format:Y-m'],
            'date' => ['nullable', 'date'],
        ]);

        $period = $filters['period'] ?? 'today';
        $monthValue = $filters['month'] ?? $now->format('Y-m');
        $dateValue = $filters['date'] ?? $now->toDateString();

        [$periodStart, $periodEnd] = $this->periodBounds($period, $monthValue, $dateValue, $tz);

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
            ->map(function (Collection $rows, $userId) use ($users) {
                $times = $rows->pluck('created_at')->sort()->values();
                $user = $users->get((int) $userId);

                return [
                    'user_id' => (int) $userId,
                    'name' => $user?->name ?? 'Tidak diketahui',
                    'email' => $user?->email,
                    'count' => $times->count(),
                    'avg_minutes' => $this->medianGapMinutes($times),
                    'files_per_hour' => $this->filesPerHour($times),
                ];
            })
            ->sortByDesc('count')
            ->values();

        $periodLabel = match ($period) {
            'month' => Carbon::createFromFormat('Y-m', $monthValue, $tz)->translatedFormat('F Y'),
            'date' => Carbon::parse($dateValue, $tz)->translatedFormat('d M Y'),
            default => 'Hari ini',
        };

        return view('entry.productivity.index', [
            'period' => $period,
            'monthValue' => $monthValue,
            'dateValue' => $dateValue,
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
    private function periodBounds(string $period, string $monthValue, string $dateValue, string $tz): array
    {
        if ($period === 'month') {
            $start = Carbon::createFromFormat('Y-m', $monthValue, $tz)->startOfMonth();

            return [$start, $start->copy()->endOfMonth()];
        }

        if ($period === 'date') {
            $day = Carbon::parse($dateValue, $tz);

            return [$day->copy()->startOfDay(), $day->copy()->endOfDay()];
        }

        $today = Carbon::now($tz);

        return [$today->copy()->startOfDay(), $today->copy()->endOfDay()];
    }

    /**
     * @param  Collection<int, Carbon>  $times
     */
    private function medianGapMinutes(Collection $times): ?float
    {
        if ($times->count() < 2) {
            return null;
        }

        $gaps = [];
        for ($i = 1, $n = $times->count(); $i < $n; $i++) {
            $seconds = $times[$i - 1]->diffInSeconds($times[$i]);
            if ($seconds > 0 && $seconds <= self::SPEED_GAP_CAP_SECONDS) {
                $gaps[] = $seconds;
            }
        }

        if ($gaps === []) {
            return null;
        }

        sort($gaps);
        $mid = intdiv(count($gaps), 2);
        $median = count($gaps) % 2 === 1
            ? $gaps[$mid]
            : (($gaps[$mid - 1] + $gaps[$mid]) / 2);

        return round($median / 60, 1);
    }

    /**
     * @param  Collection<int, Carbon>  $times
     */
    private function filesPerHour(Collection $times): ?float
    {
        if ($times->count() < 2) {
            return null;
        }

        $activeSeconds = 0;
        for ($i = 1, $n = $times->count(); $i < $n; $i++) {
            $seconds = $times[$i - 1]->diffInSeconds($times[$i]);
            if ($seconds > 0 && $seconds <= self::SPEED_GAP_CAP_SECONDS) {
                $activeSeconds += $seconds;
            }
        }

        if ($activeSeconds <= 0) {
            return null;
        }

        // ponytail: files/hour from capped inter-arrival gaps only — upgrade if shift roster exists
        return round(($times->count() - 1) / ($activeSeconds / 3600), 1);
    }
}
