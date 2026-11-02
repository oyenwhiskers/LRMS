<?php

namespace App\Http\Controllers\Entry;

use App\Http\Controllers\Controller;
use App\Models\EntryPrintJob;
use App\Models\EntryScan;
use App\Models\LegalFile;
use App\Models\Rack;
use App\Models\Shelf;
use App\Models\User;
use App\Support\LegalFileQr;
use App\Support\LocalDate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EntryPrintStationController extends Controller
{
    private const PER_PAGE = 20;

    private const MATCHING_CAP = 200;

    private const JOB_WITH = [
        'legalFile:id,reference_number,purchaser,file_identifier,qr_identifier,shelf_id',
        'legalFile.entryScan',
        'legalFile.shelf:id,rack_id,code,name',
        'legalFile.shelf.rack:id,code',
        'requester:id,name',
    ];

    public function index(Request $request): View
    {
        $filters = $this->validatedFilters($request);
        $jobs = $this->paginatedJobs($filters, self::JOB_WITH);

        $operators = User::query()
            ->whereIn('id', EntryPrintJob::query()
                ->whereIn('status', [EntryPrintJob::STATUS_PENDING, EntryPrintJob::STATUS_PRINTED])
                ->select('requested_by'))
            ->orderBy('name')
            ->get(['id', 'name']);

        $racks = Rack::query()
            ->where('is_active', true)
            ->with(['shelves' => fn ($builder) => $builder->where('is_active', true)->orderBy('code')])
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return view('entry.print-station', [
            'jobs' => $jobs,
            'operators' => $operators,
            'racks' => $racks,
            'columnsByRack' => $this->columnsByRack($racks),
            'filters' => [
                'q' => $filters['q'],
                'requested_by' => $filters['requested_by'] !== null ? (string) $filters['requested_by'] : '',
                'status' => $filters['status'],
                'review' => $filters['review'],
                'rack_id' => $filters['rack_id'] !== null ? (string) $filters['rack_id'] : '',
                'column' => $filters['column'],
                'shelf_id' => $filters['shelf_id'] !== null ? (string) $filters['shelf_id'] : '',
            ],
            'pendingCount' => EntryPrintJob::query()->where('status', EntryPrintJob::STATUS_PENDING)->count(),
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        $filters = $this->validatedFilters($request);

        $jobs = $this->paginatedJobs($filters, self::JOB_WITH);

        return response()->json([
            'jobs' => $jobs->getCollection()
                ->map(fn (EntryPrintJob $job) => $this->jobPayload($job))
                ->values(),
            'pending_count' => EntryPrintJob::query()->where('status', EntryPrintJob::STATUS_PENDING)->count(),
            'total' => $jobs->total(),
            'current_page' => $jobs->currentPage(),
            'last_page' => $jobs->lastPage(),
            'pagination' => $jobs->hasPages() ? (string) $jobs->links() : '',
        ]);
    }

    public function matching(Request $request): JsonResponse
    {
        $filters = $this->validatedFilters($request);

        $query = $this->jobsQuery($filters)->with(self::JOB_WITH);
        $total = (clone $query)->count();
        $jobs = $query->limit(self::MATCHING_CAP)->get();

        return response()->json([
            'jobs' => $jobs->map(fn (EntryPrintJob $job) => $this->jobPayload($job))->values(),
            'total' => $total,
            'capped' => $total > self::MATCHING_CAP,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'legal_file_id' => ['required', 'integer', 'exists:legal_files,id'],
            'type' => ['required', Rule::in([EntryPrintJob::TYPE_FRONT, EntryPrintJob::TYPE_SIDE])],
        ]);

        $file = LegalFile::query()->findOrFail($data['legal_file_id']);
        $this->authorizeEntryFile($request, $file);

        $existing = EntryPrintJob::query()
            ->where('legal_file_id', $file->id)
            ->where('type', $data['type'])
            ->where('status', EntryPrintJob::STATUS_PENDING)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Job sudah dihantar ke stesen cetak',
                'already_queued' => true,
                'job' => $this->jobPayload($existing->loadMissing(self::JOB_WITH)),
            ]);
        }

        $job = EntryPrintJob::create([
            'legal_file_id' => $file->id,
            'requested_by' => $request->user()->id,
            'type' => $data['type'],
            'status' => EntryPrintJob::STATUS_PENDING,
        ]);

        return response()->json([
            'message' => 'Job berjaya dihantar ke stesen cetak',
            'already_queued' => false,
            'job' => $this->jobPayload($job->load(self::JOB_WITH)),
        ], 201);
    }

    public function batchPrint(Request $request): View
    {
        $data = $request->validate([
            'ids' => ['required', 'string', 'max:4000'],
            'type' => ['nullable', Rule::in([EntryPrintJob::TYPE_FRONT, EntryPrintJob::TYPE_SIDE])],
        ]);

        $ids = collect(explode(',', $data['ids']))
            ->map(fn (string $id): int => (int) trim($id))
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        abort_if($ids->isEmpty(), 422);

        $jobs = EntryPrintJob::query()
            ->whereIn('id', $ids)
            ->whereIn('status', [
                EntryPrintJob::STATUS_PENDING,
                EntryPrintJob::STATUS_PRINTED,
            ])
            ->with('legalFile')
            ->get()
            ->sortBy(fn (EntryPrintJob $job) => $ids->search($job->id))
            ->values();

        if (isset($data['type'])) {
            $jobs = $jobs->where('type', $data['type'])->values();
        }

        $jobs = $jobs->filter(fn (EntryPrintJob $job) => $job->legalFile !== null)->values();
        abort_if($jobs->isEmpty(), 404);

        $labels = $jobs->map(fn (EntryPrintJob $job): array => [
            'file' => $job->legalFile,
            'type' => $job->type,
            'qr' => LegalFileQr::dataUri($job->legalFile),
        ])->all();

        $type = $data['type']
            ?? (collect($labels)->every(fn (array $label) => $label['type'] === EntryPrintJob::TYPE_SIDE)
                ? EntryPrintJob::TYPE_SIDE
                : EntryPrintJob::TYPE_FRONT);

        return view('entry.print-jobs.batch', [
            'labels' => $labels,
            'type' => $type,
        ]);
    }

    public function setStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:entry_print_jobs,id'],
            'status' => ['required', Rule::in([
                EntryPrintJob::STATUS_PENDING,
                EntryPrintJob::STATUS_PRINTED,
            ])],
        ]);

        $jobs = EntryPrintJob::query()
            ->whereIn('id', $data['ids'])
            ->whereIn('status', [
                EntryPrintJob::STATUS_PENDING,
                EntryPrintJob::STATUS_PRINTED,
            ])
            ->get();

        $now = now();
        $marked = [];
        foreach ($jobs as $job) {
            if ($job->status === $data['status']) {
                continue;
            }

            if ($data['status'] === EntryPrintJob::STATUS_PRINTED) {
                $job->update([
                    'status' => EntryPrintJob::STATUS_PRINTED,
                    'printed_at' => $now,
                    'printed_by' => $request->user()->id,
                ]);
            } else {
                $job->update([
                    'status' => EntryPrintJob::STATUS_PENDING,
                    'printed_at' => null,
                    'printed_by' => null,
                ]);
            }

            $marked[] = $job->id;
        }

        return response()->json([
            'marked' => $marked,
            'status' => $data['status'],
        ]);
    }

    public function destroy(EntryPrintJob $job): JsonResponse
    {
        abort_unless($job->isPending() || $job->isPrinted(), 404);

        $job->update(['status' => EntryPrintJob::STATUS_CANCELLED]);

        return response()->json([
            'message' => 'Job dipadam dari stesen',
            'id' => $job->id,
        ]);
    }

    /**
     * @return array{
     *     q: string,
     *     status: string,
     *     review: string,
     *     requested_by: int|null,
     *     rack_id: int|null,
     *     column: string,
     *     shelf_id: int|null
     * }
     */
    private function validatedFilters(Request $request): array
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'requested_by' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', Rule::in(['pending', 'printed', 'all'])],
            'review' => ['nullable', Rule::in(['all', 'verified', 'unverified'])],
            'rack_id' => ['nullable', 'integer', 'exists:racks,id'],
            'column' => ['nullable', 'string', 'max:10'],
            'shelf_id' => [
                'nullable',
                'integer',
                Rule::exists('shelves', 'id')->when(
                    $request->filled('rack_id'),
                    fn ($rule) => $rule->where('rack_id', $request->integer('rack_id')),
                ),
            ],
        ]);

        $rackId = $filters['rack_id'] ?? null;
        $column = trim((string) ($filters['column'] ?? ''));
        $shelfId = $filters['shelf_id'] ?? null;

        // Column requires a rack; otherwise ignore.
        if ($column !== '' && $rackId === null) {
            $column = '';
        }

        return [
            'q' => trim((string) ($filters['q'] ?? '')),
            'status' => $filters['status'] ?? 'pending',
            'review' => $filters['review'] ?? 'all',
            'requested_by' => $filters['requested_by'] ?? null,
            'rack_id' => $rackId,
            'column' => $column,
            'shelf_id' => $shelfId,
        ];
    }

    /**
     * @param  array{
     *     q: string,
     *     status: string,
     *     review: string,
     *     requested_by: int|null,
     *     rack_id: int|null,
     *     column: string,
     *     shelf_id: int|null
     * }  $filters
     * @return Builder<EntryPrintJob>
     */
    private function jobsQuery(array $filters)
    {
        $status = $filters['status'];
        $query = EntryPrintJob::query()
            ->when(
                $status === 'printed',
                fn ($builder) => $builder->where('status', EntryPrintJob::STATUS_PRINTED),
            )
            ->when(
                $status === 'all',
                fn ($builder) => $builder->whereIn('status', [
                    EntryPrintJob::STATUS_PENDING,
                    EntryPrintJob::STATUS_PRINTED,
                ]),
            )
            ->when(
                $status === 'pending',
                fn ($builder) => $builder->where('status', EntryPrintJob::STATUS_PENDING),
            )
            ->when(
                filled($filters['requested_by']),
                fn ($builder) => $builder->where('requested_by', $filters['requested_by']),
            )
            ->when(
                $filters['review'] === 'verified',
                fn ($builder) => $builder->whereHas(
                    'legalFile.entryScan',
                    fn ($scan) => $scan->where('review_status', EntryScan::REVIEW_VERIFIED),
                ),
            )
            ->when(
                $filters['review'] === 'unverified',
                fn ($builder) => $builder->whereDoesntHave(
                    'legalFile.entryScan',
                    fn ($scan) => $scan->where('review_status', EntryScan::REVIEW_VERIFIED),
                ),
            )
            ->when(
                $filters['q'] !== '',
                function ($builder) use ($filters): void {
                    $contains = '%'.addcslashes($filters['q'], '\\%_').'%';
                    $builder->whereHas(
                        'legalFile',
                        fn ($file) => $file->where('reference_number', 'like', $contains),
                    );
                },
            );

        if (filled($filters['shelf_id'])) {
            $query->whereHas(
                'legalFile',
                fn ($file) => $file->where('shelf_id', $filters['shelf_id']),
            );
        } elseif ($filters['column'] !== '' && filled($filters['rack_id'])) {
            $columnLike = Shelf::columnNameLike($filters['column']);
            $rackId = $filters['rack_id'];
            $query->whereHas(
                'legalFile.shelf',
                fn ($shelf) => $shelf
                    ->where('rack_id', $rackId)
                    ->where('name', 'like', $columnLike),
            );
        } elseif (filled($filters['rack_id'])) {
            $rackId = $filters['rack_id'];
            $query->whereHas(
                'legalFile.shelf',
                fn ($shelf) => $shelf->where('rack_id', $rackId),
            );
        }

        return match ($status) {
            'printed' => $query->orderByDesc('printed_at'),
            'all' => $query
                ->orderByRaw('case when status = ? then 0 else 1 end', [EntryPrintJob::STATUS_PENDING])
                ->orderByDesc('updated_at'),
            default => $query->orderBy('created_at'),
        };
    }

    /**
     * @param  array{
     *     q: string,
     *     status: string,
     *     review: string,
     *     requested_by: int|null,
     *     rack_id: int|null,
     *     column: string,
     *     shelf_id: int|null
     * }  $filters
     * @param  list<string>  $with
     * @return LengthAwarePaginator<int, EntryPrintJob>
     */
    private function paginatedJobs(array $filters, array $with)
    {
        $paginator = $this->jobsQuery($filters)
            ->with($with)
            ->paginate(self::PER_PAGE)
            ->withPath(route('entry.print-station'))
            ->withQueryString();

        // After mark/print, the current page can become empty — snap back.
        if ($paginator->isEmpty() && $paginator->currentPage() > 1 && $paginator->lastPage() >= 1) {
            $paginator = $this->jobsQuery($filters)
                ->with($with)
                ->paginate(self::PER_PAGE, ['*'], 'page', $paginator->lastPage())
                ->withPath(route('entry.print-station'))
                ->withQueryString();
        }

        return $paginator;
    }

    /**
     * @param  Collection<int, Rack>  $racks
     * @return array<int, list<string>>
     */
    private function columnsByRack($racks): array
    {
        $columns = [];

        foreach ($racks as $rack) {
            $columns[$rack->id] = $rack->shelves
                ->map(fn (Shelf $shelf) => $shelf->columnLabel())
                ->filter()
                ->unique()
                ->sort(SORT_NATURAL)
                ->values()
                ->all();
        }

        return $columns;
    }

    /**
     * @return array<string, mixed>
     */
    private function jobPayload(EntryPrintJob $job): array
    {
        // Relative: iframe print must hit the browser host, not a stale APP_URL absolute.
        $printUrl = route('entry.files.label', [
            'file' => $job->legal_file_id,
            'type' => $job->type,
        ], absolute: false);

        $scan = $job->legalFile?->entryScan;
        $reviewStatus = $scan?->review_status ?: EntryScan::REVIEW_PENDING;
        $shelf = $job->legalFile?->shelf;
        $location = ($shelf && $shelf->rack)
            ? $shelf->rack->code.'/'.$shelf->code
            : '—';

        return [
            'id' => $job->id,
            'type' => $job->type,
            'type_label' => $job->type === EntryPrintJob::TYPE_SIDE ? 'Sisi' : 'Hadapan',
            'status' => $job->status,
            'status_label' => $job->statusLabel(),
            'is_pending' => $job->isPending(),
            'review_status' => $reviewStatus,
            'review_status_label' => $scan?->reviewStatusLabel() ?? 'Belum semak',
            'is_verified' => $reviewStatus === EntryScan::REVIEW_VERIFIED,
            'created_at' => LocalDate::format($job->created_at),
            'printed_at' => $job->printed_at ? LocalDate::format($job->printed_at) : null,
            'requested_by' => $job->requested_by,
            'operator' => $job->requester?->name ?? '—',
            'legal_file_id' => $job->legal_file_id,
            'reference_number' => $job->legalFile?->reference_number ?? '—',
            'location' => $location,
            'print_url' => $printUrl,
            'preview_url' => $printUrl.(str_contains($printUrl, '?') ? '&' : '?').'embed=1',
            'delete_url' => ($job->isPending() || $job->isPrinted())
                ? route('entry.print-jobs.destroy', $job, absolute: false)
                : null,
        ];
    }

    private function authorizeEntryFile(Request $request, LegalFile $file): void
    {
        $user = $request->user();
        $owns = EntryScan::query()
            ->where('user_id', $user->id)
            ->where('legal_file_id', $file->id)
            ->whereIn('status', [EntryScan::STATUS_SAVED, EntryScan::STATUS_STORED])
            ->exists();

        abort_unless($owns || $user->isAdmin(), 403);
    }
}
