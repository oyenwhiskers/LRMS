<?php

namespace App\Http\Controllers;

use App\Models\CaseCategory;
use App\Models\FileMovement;
use App\Models\LegalFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): View|JsonResponse
    {
        if (! $request->user()->can('dashboard.view')) {
            abort_if($request->expectsJson(), 403);

            $request->user()->loadMissing('staff.position');

            return view('home');
        }

        $todayStart = Carbon::today()->startOfDay();
        $todayEnd = Carbon::today()->endOfDay();
        $weekStart = Carbon::today()->startOfWeek();
        $monthStart = Carbon::today()->startOfMonth();
        $yearStart = Carbon::today()->startOfYear();
        $chartMode = $this->chartMode($request->string('chart')->toString());
        $selectedMonth = $this->selectedMonth($request->string('month')->toString());
        $chart = $this->registrationChart($chartMode, $selectedMonth, $todayEnd);
        $chartMonthOptions = collect(range(0, 17))->map(function (int $offset) use ($todayEnd): array {
            $month = $todayEnd->copy()->startOfMonth()->subMonths($offset);

            return [
                'value' => $month->format('Y-m'),
                'label' => $month->format('F Y'),
            ];
        });

        if ($request->expectsJson()) {
            return response()
                ->json([
                    'registrationChart' => $chart,
                    'chartMonthOptions' => $chartMonthOptions->values(),
                ])
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');
        }

        return view('dashboard', [
            'registry' => [
                'registered_today' => LegalFile::active()
                    ->whereBetween('created_at', [$todayStart, $todayEnd])
                    ->count(),
                'registered_week' => LegalFile::active()
                    ->whereBetween('created_at', [$weekStart, $todayEnd])
                    ->count(),
                'registered_month' => LegalFile::active()
                    ->whereBetween('created_at', [$monthStart, $todayEnd])
                    ->count(),
                'registered_year' => LegalFile::active()
                    ->whereBetween('created_at', [$yearStart, $todayEnd])
                    ->count(),
            ],
            'statusSummary' => [
                'active_registered' => LegalFile::active()
                    ->where('status', LegalFile::STATUS_REGISTERED)
                    ->count(),
                'pending_review' => LegalFile::active()
                    ->where('status', LegalFile::STATUS_PENDING)
                    ->count(),
                'ready_for_storage' => LegalFile::active()
                    ->where('status', LegalFile::STATUS_COMPLETED)
                    ->count(),
                'currently_stored' => LegalFile::active()
                    ->where('status', LegalFile::STATUS_STORED)
                    ->count(),
                'currently_borrowed' => LegalFile::active()
                    ->where('status', LegalFile::STATUS_BORROWED)
                    ->count(),
                'missing' => LegalFile::active()
                    ->where('status', LegalFile::STATUS_MISSING)
                    ->count(),
            ],
            'movementSummary' => [
                'borrowed_today' => FileMovement::query()
                    ->where('type', 'borrow')
                    ->whereBetween('occurred_at', [$todayStart, $todayEnd])
                    ->count(),
                'returned_today' => FileMovement::query()
                    ->where('type', 'return')
                    ->whereBetween('occurred_at', [$todayStart, $todayEnd])
                    ->count(),
            ],
            'categoryRegistry' => $this->categoryRegistry($todayStart, $weekStart, $monthStart, $todayEnd),
            'registrationChart' => $chart,
            'chartMonthOptions' => $chartMonthOptions,
            'activities' => FileMovement::query()
                ->select(['id', 'legal_file_id', 'employee_id', 'receiver_id', 'processed_by', 'type', 'occurred_at'])
                ->with([
                    'legalFile:id,reference_number,matter_type,vendor,current_holder_id,status',
                    'legalFile.currentHolder:id,full_name,position_id',
                    'legalFile.currentHolder.position:id,name',
                    'employee:id,full_name,position_id',
                    'employee.position:id,name',
                    'receiver:id,full_name',
                ])
                ->latest('occurred_at')
                ->limit(5)
                ->get(),
        ]);
    }

    private function categoryRegistry(Carbon $todayStart, Carbon $weekStart, Carbon $monthStart, Carbon $todayEnd): Collection
    {
        $categories = CaseCategory::query()->orderBy('code')->get(['id', 'code', 'name']);

        $countsFor = function (Carbon $from, Carbon $to): Collection {
            return LegalFile::active()
                ->whereBetween('created_at', [$from, $to])
                ->whereNotNull('case_category_id')
                ->selectRaw('case_category_id, COUNT(*) as aggregate')
                ->groupBy('case_category_id')
                ->pluck('aggregate', 'case_category_id');
        };

        $todayCounts = $countsFor($todayStart, $todayEnd);
        $weekCounts = $countsFor($weekStart, $todayEnd);
        $monthCounts = $countsFor($monthStart, $todayEnd);

        return $categories->map(fn (CaseCategory $category): array => [
            'id' => $category->id,
            'code' => $category->code,
            'name' => $category->name,
            'today' => (int) ($todayCounts[$category->id] ?? 0),
            'week' => (int) ($weekCounts[$category->id] ?? 0),
            'month' => (int) ($monthCounts[$category->id] ?? 0),
        ])->filter(fn (array $row): bool => $row['today'] > 0 || $row['week'] > 0 || $row['month'] > 0)
            ->sortBy([
                ['month', 'desc'],
                ['week', 'desc'],
                ['today', 'desc'],
                ['code', 'asc'],
            ])
            ->values();
    }

    private function chartMode(string $mode): string
    {
        if ($mode === '12m') {
            return 'year';
        }

        return in_array($mode, ['30d', 'year', 'month'], true) ? $mode : '30d';
    }

    private function selectedMonth(string $month): Carbon
    {
        if (preg_match('/^\d{4}-\d{2}$/', $month) !== 1) {
            return Carbon::today()->startOfMonth();
        }

        try {
            return Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable) {
            return Carbon::today()->startOfMonth();
        }
    }

    private function registrationChart(string $mode, Carbon $selectedMonth, Carbon $todayEnd): array
    {
        return match ($mode) {
            'year' => $this->buildThisYearChart($todayEnd),
            'month' => $this->buildSpecificMonthChart($selectedMonth),
            default => $this->buildThirtyDayChart($todayEnd),
        };
    }

    private function buildThirtyDayChart(Carbon $todayEnd): array
    {
        $start = $todayEnd->copy()->subDays(29)->startOfDay();
        $counts = $this->registeredCountsByDay($start, $todayEnd->copy()->endOfDay());
        $points = collect(range(0, 29))->map(function (int $offset) use ($start, $counts): array {
            $date = $start->copy()->addDays($offset);
            $key = $date->toDateString();

            return [
                'label' => $date->format('d M'),
                'short_label' => $date->format('d M'),
                'value' => (int) ($counts[$key] ?? 0),
            ];
        });

        return [
            'mode' => '30d',
            'type' => 'line',
            'title' => 'Case registration trend',
            'period_label' => 'Last 30 days',
            'points' => $points,
            'selected_month' => $todayEnd->format('Y-m'),
        ];
    }

    private function buildThisYearChart(Carbon $todayEnd): array
    {
        $start = $todayEnd->copy()->startOfYear();
        $end = $todayEnd->copy()->endOfMonth();
        $counts = $this->registeredCountsByMonth($start, $end);
        $points = collect(range(0, $todayEnd->month - 1))->map(function (int $offset) use ($start, $counts): array {
            $month = $start->copy()->addMonths($offset);
            $key = $month->format('Y-m');

            return [
                'label' => $month->format('M Y'),
                'short_label' => $month->format('M'),
                'value' => (int) ($counts[$key] ?? 0),
            ];
        });

        return [
            'mode' => 'year',
            'type' => 'line',
            'title' => 'Case registration trend',
            'period_label' => 'This year to date',
            'points' => $points,
            'selected_month' => $todayEnd->format('Y-m'),
        ];
    }

    private function buildSpecificMonthChart(Carbon $selectedMonth): array
    {
        $start = $selectedMonth->copy()->startOfMonth();
        $end = $selectedMonth->copy()->endOfMonth();
        $counts = $this->registeredCountsByDay($start, $end);
        $points = collect(range(1, $selectedMonth->daysInMonth))->map(function (int $day) use ($selectedMonth, $counts): array {
            $date = $selectedMonth->copy()->day($day);
            $key = $date->toDateString();

            return [
                'label' => $date->format('d M'),
                'short_label' => $date->format('d'),
                'value' => (int) ($counts[$key] ?? 0),
            ];
        });

        return [
            'mode' => 'month',
            'type' => 'line',
            'title' => 'Case registration trend',
            'period_label' => $selectedMonth->format('F Y'),
            'points' => $points,
            'selected_month' => $selectedMonth->format('Y-m'),
        ];
    }

    private function registeredCountsByDay(Carbon $start, Carbon $end): Collection
    {
        return LegalFile::query()
            ->whereBetween('created_at', [$start, $end])
            ->get(['created_at'])
            ->groupBy(fn (LegalFile $file) => $file->created_at->toDateString())
            ->map(fn (Collection $items) => $items->count());
    }

    private function registeredCountsByMonth(Carbon $start, Carbon $end): Collection
    {
        return LegalFile::query()
            ->whereBetween('created_at', [$start, $end])
            ->get(['created_at'])
            ->groupBy(fn (LegalFile $file) => $file->created_at->format('Y-m'))
            ->map(fn (Collection $items) => $items->count());
    }
}
