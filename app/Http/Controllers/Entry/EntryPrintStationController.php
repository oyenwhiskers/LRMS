<?php

namespace App\Http\Controllers\Entry;

use App\Http\Controllers\Controller;
use App\Models\EntryPrintJob;
use App\Models\EntryScan;
use App\Models\LegalFile;
use App\Models\User;
use App\Support\LegalFileQr;
use App\Support\LocalDate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EntryPrintStationController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->validatedFilters($request);
        $jobs = $this->jobsQuery($filters)
            ->with([
                'legalFile:id,reference_number,purchaser,file_identifier,qr_identifier',
                'legalFile.entryScan',
                'requester:id,name',
            ])
            ->get();

        $operators = User::query()
            ->whereIn('id', EntryPrintJob::query()
                ->whereIn('status', [EntryPrintJob::STATUS_PENDING, EntryPrintJob::STATUS_PRINTED])
                ->select('requested_by'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('entry.print-station', [
            'jobs' => $jobs,
            'operators' => $operators,
            'filters' => [
                'q' => $filters['q'],
                'requested_by' => $filters['requested_by'] !== null ? (string) $filters['requested_by'] : '',
                'status' => $filters['status'],
                'review' => $filters['review'],
            ],
            'pendingCount' => EntryPrintJob::query()->where('status', EntryPrintJob::STATUS_PENDING)->count(),
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        $filters = $this->validatedFilters($request);

        $jobs = $this->jobsQuery($filters)
            ->with([
                'legalFile:id,reference_number,purchaser,file_identifier',
                'legalFile.entryScan',
                'requester:id,name',
            ])
            ->get()
            ->map(fn (EntryPrintJob $job) => $this->jobPayload($job));

        return response()->json([
            'jobs' => $jobs,
            'pending_count' => EntryPrintJob::query()->where('status', EntryPrintJob::STATUS_PENDING)->count(),
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
                'job' => $this->jobPayload($existing->loadMissing([
                    'legalFile:id,reference_number,purchaser,file_identifier',
                    'legalFile.entryScan',
                    'requester:id,name',
                ])),
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
            'job' => $this->jobPayload($job->load([
                'legalFile:id,reference_number,purchaser,file_identifier',
                'legalFile.entryScan',
                'requester:id,name',
            ])),
        ], 201);
    }

    public function batchPrint(Request $request): View
    {
        $data = $request->validate([
            'ids' => ['required', 'string', 'max:2000'],
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
     * @return array{q: string, status: string, review: string, requested_by: int|null}
     */
    private function validatedFilters(Request $request): array
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'requested_by' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', Rule::in(['pending', 'printed', 'all'])],
            'review' => ['nullable', Rule::in(['all', 'verified', 'unverified'])],
        ]);

        return [
            'q' => trim((string) ($filters['q'] ?? '')),
            'status' => $filters['status'] ?? 'pending',
            'review' => $filters['review'] ?? 'all',
            'requested_by' => $filters['requested_by'] ?? null,
        ];
    }

    /**
     * @param  array{q: string, status: string, review: string, requested_by: int|null}  $filters
     * @return \Illuminate\Database\Eloquent\Builder<EntryPrintJob>
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

        return match ($status) {
            'printed' => $query->orderByDesc('printed_at')->limit(50),
            'all' => $query
                ->orderByRaw("case when status = ? then 0 else 1 end", [EntryPrintJob::STATUS_PENDING])
                ->orderByDesc('updated_at')
                ->limit(100),
            default => $query->orderBy('created_at'),
        };
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
