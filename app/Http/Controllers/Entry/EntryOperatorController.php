<?php

namespace App\Http\Controllers\Entry;

use App\Http\Controllers\Controller;
use App\Models\CaseCategory;
use App\Models\EntryScan;
use App\Models\LegalFile;
use App\Models\Rack;
use App\Models\Shelf;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class EntryOperatorController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'rack_id' => ['nullable', 'integer', 'exists:racks,id'],
            'shelf_id' => ['nullable', 'integer', 'exists:shelves,id'],
            'case_type' => ['nullable', Rule::in(LegalFile::caseTypes())],
        ]);

        $base = EntryScan::query()
            ->whereIn('status', [EntryScan::STATUS_SAVED, EntryScan::STATUS_STORED]);

        $this->applyBrowseFilters($base, $filters, includeSearch: false);

        $fileCount = (clone $base)->count();
        $operatorCount = (clone $base)->distinct('user_id')->count('user_id');
        $pendingShelfCount = (clone $base)->where('status', EntryScan::STATUS_SAVED)->count();
        $onShelfCount = (clone $base)->where('status', EntryScan::STATUS_STORED)->count();

        $scanWith = [
            'user:id,name,email',
            'legalFile.parties',
            'legalFile.caseCategory',
            'shelf.rack',
        ];

        // ponytail: modal lists capped at 50 — enough for checker glance; full list stays in the page table
        $metricAllScans = (clone $base)->with($scanWith)->latest()->limit(50)->get();
        $metricPendingScans = (clone $base)
            ->where('status', EntryScan::STATUS_SAVED)
            ->with($scanWith)
            ->latest()
            ->limit(50)
            ->get();
        $metricStoredScans = (clone $base)
            ->where('status', EntryScan::STATUS_STORED)
            ->with($scanWith)
            ->latest()
            ->limit(50)
            ->get();

        $operatorBreakdown = (clone $base)
            ->select('user_id', DB::raw('COUNT(*) as registrations_count'))
            ->groupBy('user_id')
            ->orderByDesc('registrations_count')
            ->get();

        $operators = User::query()
            ->where('role', User::ROLE_ENTRY)
            ->whereIn(
                'id',
                EntryScan::query()
                    ->whereIn('status', [EntryScan::STATUS_SAVED, EntryScan::STATUS_STORED])
                    ->select('user_id')
                    ->distinct(),
            )
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $operatorRows = $operatorBreakdown->map(function ($row) use ($operators) {
            $user = $operators->firstWhere('id', (int) $row->user_id);

            return [
                'user_id' => (int) $row->user_id,
                'name' => $user?->name ?? 'Tidak diketahui',
                'email' => $user?->email,
                'count' => (int) $row->registrations_count,
            ];
        });

        $query = EntryScan::query()
            ->whereIn('status', [EntryScan::STATUS_SAVED, EntryScan::STATUS_STORED])
            ->with($scanWith);

        $this->applyBrowseFilters($query, $filters, includeSearch: true);

        $scans = $query->latest()->paginate(20)->withQueryString();

        $reviewScans = $scans->getCollection()
            ->concat($metricAllScans)
            ->concat($metricPendingScans)
            ->concat($metricStoredScans)
            ->unique('id')
            ->values();

        $racks = Rack::query()
            ->where('is_active', true)
            ->with(['shelves' => fn ($builder) => $builder->where('is_active', true)->orderBy('code')])
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        $shelves = Shelf::query()
            ->where('is_active', true)
            ->when(
                ! empty($filters['rack_id']),
                fn ($builder) => $builder->where('rack_id', $filters['rack_id']),
            )
            ->with('rack:id,code')
            ->orderBy('code')
            ->get(['id', 'rack_id', 'code', 'name']);

        $categories = CaseCategory::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return view('entry.operators.index', [
            'scans' => $scans,
            'reviewScans' => $reviewScans,
            'fileCount' => $fileCount,
            'operatorCount' => $operatorCount,
            'pendingShelfCount' => $pendingShelfCount,
            'onShelfCount' => $onShelfCount,
            'metricAllScans' => $metricAllScans,
            'metricPendingScans' => $metricPendingScans,
            'metricStoredScans' => $metricStoredScans,
            'operatorRows' => $operatorRows,
            'operators' => $operators,
            'racks' => $racks,
            'shelves' => $shelves,
            'categories' => $categories,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'user_id' => $filters['user_id'] ?? '',
                'date_from' => $filters['date_from'] ?? '',
                'date_to' => $filters['date_to'] ?? '',
                'rack_id' => $filters['rack_id'] ?? '',
                'shelf_id' => $filters['shelf_id'] ?? '',
                'case_type' => $filters['case_type'] ?? '',
            ],
        ]);
    }

    public function document(EntryScan $scan): Response
    {
        abort_unless(
            in_array($scan->status, [EntryScan::STATUS_SAVED, EntryScan::STATUS_STORED], true),
            404,
        );

        $path = (string) ($scan->document_path ?? '');
        abort_if($path === '' || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    /**
     * @param  array{
     *     q?: string|null,
     *     user_id?: int|string|null,
     *     date_from?: string|null,
     *     date_to?: string|null,
     *     rack_id?: int|string|null,
     *     shelf_id?: int|string|null,
     *     case_type?: string|null
     * }  $filters
     */
    private function applyBrowseFilters($query, array $filters, bool $includeSearch): void
    {
        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->where(
                'created_at',
                '>=',
                Carbon::parse($filters['date_from'], config('app.timezone'))->startOfDay(),
            );
        }

        if (! empty($filters['date_to'])) {
            $query->where(
                'created_at',
                '<=',
                Carbon::parse($filters['date_to'], config('app.timezone'))->endOfDay(),
            );
        }

        if (! empty($filters['shelf_id'])) {
            $query->where('shelf_id', $filters['shelf_id']);
        } elseif (! empty($filters['rack_id'])) {
            $query->whereHas('shelf', fn ($builder) => $builder->where('rack_id', $filters['rack_id']));
        }

        if (! empty($filters['case_type'])) {
            $query->whereHas(
                'legalFile',
                fn ($builder) => $builder->where('case_type', $filters['case_type']),
            );
        }

        if (! $includeSearch) {
            return;
        }

        if ($search = trim((string) ($filters['q'] ?? ''))) {
            $contains = '%'.addcslashes($search, '\\%_').'%';
            $query->whereHas('legalFile', function ($builder) use ($contains): void {
                $builder->where('reference_number', 'like', $contains)
                    ->orWhere('purchaser', 'like', $contains);
            });
        }
    }
}
