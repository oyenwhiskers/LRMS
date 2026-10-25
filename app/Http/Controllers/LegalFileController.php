<?php

namespace App\Http\Controllers;

use App\Models\CaseCategory;
use App\Models\FileMovement;
use App\Models\LegalFile;
use App\Models\Shelf;
use App\Models\Staff;
use App\Support\LegalFileQr;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class LegalFileController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        abort_unless($request->user()->can('files.view'), 403);
        $viewMode = $request->input('view') === 'customer' ? 'customer' : 'case';
        $isFeed = $request->boolean('feed');
        $perPage = 10;
        $query = LegalFile::query()->active();
        if ($search = trim((string) $request->input('search'))) {
            $escapedSearch = addcslashes($search, '\\%_');
            $containsSearch = "%{$escapedSearch}%";

            if (DB::connection()->getDriverName() === 'mysql' && mb_strlen($search) >= 3) {
                $query->where(function ($q) use ($search, $escapedSearch, $containsSearch): void {
                    $q->where('reference_number', 'like', "{$escapedSearch}%")
                        ->orWhereFullText(
                            ['reference_number', 'purchaser', 'vendor', 'property'],
                            $search,
                        )
                        ->orWhere('bank', 'like', $containsSearch)
                        ->orWhere('title_number', 'like', $containsSearch)
                        ->orWhere('suite_number', 'like', $containsSearch)
                        ->orWhere('ic_number', 'like', $containsSearch)
                        ->orWhereHas(
                            'currentHolder',
                            fn ($holder) => $holder->where('full_name', 'like', $containsSearch),
                        );
                });
            } else {
                $query->where(function ($q) use ($containsSearch): void {
                    $q->where('reference_number', 'like', $containsSearch)
                        ->orWhere('purchaser', 'like', $containsSearch)
                        ->orWhere('vendor', 'like', $containsSearch)
                        ->orWhere('bank', 'like', $containsSearch)
                        ->orWhere('property', 'like', $containsSearch)
                        ->orWhere('title_number', 'like', $containsSearch)
                        ->orWhere('suite_number', 'like', $containsSearch)
                        ->orWhere('ic_number', 'like', $containsSearch)
                        ->orWhereHas(
                            'currentHolder',
                            fn ($holder) => $holder->where('full_name', 'like', $containsSearch),
                        );
                });
            }
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($viewMode === 'customer') {
            $customers = $query
                ->selectRaw("
                    purchaser,
                    COUNT(*) as total_cases,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as stored_cases,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as borrowed_cases,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as missing_cases,
                    MAX(updated_at) as last_activity_at
                ", [
                    LegalFile::STATUS_STORED,
                    LegalFile::STATUS_BORROWED,
                    LegalFile::STATUS_MISSING,
                ])
                ->groupBy('purchaser')
                ->orderBy('purchaser')
                ->paginate($perPage)
                ->withQueryString();

            if ($isFeed) {
                return response()->json([
                    'html' => view('files.partials.mobile-customer-cards', [
                        'customers' => $customers,
                    ])->render(),
                    'next_page' => $customers->hasMorePages() ? $customers->currentPage() + 1 : null,
                ]);
            }

            return view('files.index', [
                'viewMode' => $viewMode,
                'customers' => $customers,
            ]);
        }

        $files = $query
            ->with(['currentHolder', 'shelf.rack', 'caseCategory'])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        if ($isFeed) {
            return response()->json([
                'html' => view('files.partials.mobile-case-cards', [
                    'files' => $files,
                ])->render(),
                'next_page' => $files->hasMorePages() ? $files->currentPage() + 1 : null,
            ]);
        }

        return view('files.index', [
            'viewMode' => $viewMode,
            'files' => $files,
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('files.create'), 403);

        return view('files.form', $this->formData(new LegalFile));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('files.create'), 403);
        $data = $this->validated($request);
        $relatedIds = $data['related_file_ids'] ?? [];
        unset($data['related_file_ids']);
        $data['created_by'] = $data['updated_by'] = $request->user()->id;

        $file = DB::transaction(function () use ($data, $relatedIds, $request): LegalFile {
            $category = CaseCategory::query()
                ->whereKey($data['case_category_id'])
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();

            $data['reference_number'] = $category->nextReference();
            $data['matter_type'] = $category->name;

            $file = LegalFile::create($data);
            $file->syncRelatedFiles($relatedIds);

            FileMovement::create([
                'legal_file_id' => $file->id,
                'type' => 'registered',
                'processed_by' => $request->user()->id,
                'previous_status' => null,
                'new_status' => LegalFile::STATUS_REGISTERED,
                'occurred_at' => $file->created_at,
            ]);

            return $file;
        });

        return redirect()->route('files.show', $file)->with('success', 'Case registered. File is not stored yet.');
    }

    public function show(Request $request, LegalFile $file): View
    {
        abort_unless($request->user()->can('files.view'), 403);
        $file->load([
            'shelf.rack',
            'currentHolder',
            'personInCharge',
            'caseCategory',
            'linkedAsPrimary',
            'linkedAsRelated',
        ]);
        $movements = $file->movements()
            ->with([
                'employee:id,full_name',
                'receiver:id,full_name',
                'previousHolder:id,full_name',
                'processor:id,name',
                'shelf.rack',
            ])
            ->paginate(30)
            ->withQueryString();
        $qr = $request->user()->can('labels.print') ? LegalFileQr::dataUri($file) : null;
        $shelves = Shelf::with('rack')->where('is_active', true)->orderBy('code')->get();

        return view('files.show', compact('file', 'movements', 'qr', 'shelves'));
    }

    public function edit(Request $request, LegalFile $file): View
    {
        abort_unless($request->user()->can('files.update'), 403);
        $file->load(['linkedAsPrimary', 'linkedAsRelated', 'caseCategory']);

        return view('files.form', $this->formData($file));
    }

    public function update(Request $request, LegalFile $file): RedirectResponse
    {
        abort_unless($request->user()->can('files.update'), 403);
        $data = $this->validated($request, $file);
        $relatedIds = $data['related_file_ids'] ?? [];
        unset($data['related_file_ids'], $data['case_category_id'], $data['case_type']);

        DB::transaction(function () use ($file, $data, $relatedIds, $request): void {
            $file->update($data + ['updated_by' => $request->user()->id]);
            $file->syncRelatedFiles($relatedIds);
        });

        return redirect()->route('files.show', $file)->with('success', 'File record updated.');
    }

    public function destroy(Request $request, LegalFile $file): RedirectResponse
    {
        abort_unless($request->user()->can('files.delete'), 403);

        $reference = $file->reference_number;

        DB::transaction(function () use ($file): void {
            DB::table('legal_file_links')
                ->where(function ($query) use ($file): void {
                    $query->where('legal_file_id', $file->id)
                        ->orWhere('related_legal_file_id', $file->id);
                })
                ->delete();

            $file->movements()->delete();
            $file->delete();
        });

        return redirect()
            ->route('files.index')
            ->with('success', "File {$reference} deleted.");
    }

    public function label(Request $request, LegalFile $file): View
    {
        abort_unless($request->user()->can('labels.print'), 403);

        return view('files.labels.print', $this->labelViewData($request, $file));
    }

    public function labelPdf(Request $request, LegalFile $file): Response
    {
        abort_unless($request->user()->can('labels.print'), 403);

        $data = $this->labelViewData($request, $file) + ['pdf' => true];

        return Pdf::loadView('files.labels.print', $data)
            ->setPaper($data['type'] === 'side' ? [0, 0, 226.77, 113.39] : [0, 0, 425.20, 283.46])
            ->download($file->file_identifier.'-'.$data['type'].'-label.pdf');
    }

    public function lookup(Request $request, string $identifier): JsonResponse
    {
        abort_unless(
            $request->user()->can('movements.borrow')
            || $request->user()->can('movements.pic_intake')
            || $request->user()->can('movements.pic_return_receive'),
            403,
        );
        $file = LegalFile::with(['currentHolder', 'shelf.rack'])->where('qr_identifier', $identifier)->active()->firstOrFail();

        return response()->json([
            'identifier' => $file->file_identifier,
            'reference_number' => $file->reference_number,
            'status' => $file->status,
            'current_holder' => $file->currentHolder?->full_name,
            'location' => $file->shelf?->full_location,
        ]);
    }

    public function missing(Request $request, LegalFile $file): RedirectResponse
    {
        abort_unless($request->user()->can('missing.manage'), 403);
        abort_unless(in_array($file->status, [
            LegalFile::STATUS_STORED,
            LegalFile::STATUS_BORROWED,
        ], true), 422, 'Only stored or borrowed files can be marked missing.');
        $data = $request->validate(['notes' => ['required', 'string', 'max:1000']]);
        $this->changeStatus($file, LegalFile::STATUS_MISSING, 'missing', $data['notes'], $request);

        return back()->with('success', 'File marked missing.');
    }

    public function found(Request $request, LegalFile $file): RedirectResponse
    {
        abort_unless($request->user()->can('missing.manage'), 403);
        abort_unless($file->status === LegalFile::STATUS_MISSING, 422, 'Only missing files can be marked found.');
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
            'shelf_id' => ['required', 'exists:shelves,id'],
        ]);
        $shelf = Shelf::with('rack')->findOrFail($data['shelf_id']);
        abort_unless($shelf->is_active && $shelf->rack->is_active, 422, 'Select an active storage location.');
        $this->changeStatus($file, LegalFile::STATUS_STORED, 'found', $data['notes'] ?? null, $request, $shelf->id);

        return back()->with('success', 'File marked found and restored to storage.');
    }

    private function changeStatus(LegalFile $file, string $status, string $type, ?string $notes, Request $request, ?int $shelfId = null): void
    {
        DB::transaction(function () use ($file, $status, $type, $notes, $request, $shelfId): void {
            $locked = LegalFile::lockForUpdate()->findOrFail($file->id);
            FileMovement::create([
                'legal_file_id' => $locked->id,
                'type' => $type,
                'employee_id' => $locked->current_holder_id,
                'previous_holder_id' => $locked->current_holder_id,
                'processed_by' => $request->user()->id,
                'shelf_id' => $shelfId ?? $locked->shelf_id,
                'previous_status' => $locked->status,
                'new_status' => $status,
                'notes' => $notes,
                'occurred_at' => now(),
            ]);
            $locked->update([
                'status' => $status,
                'shelf_id' => $shelfId ?? $locked->shelf_id,
                'current_holder_id' => null,
                'updated_by' => $request->user()->id,
            ]);
        });
    }

    private function validated(Request $request, ?LegalFile $file = null): array
    {
        $caseType = $file?->exists
            ? (string) $file->case_type
            : (string) $request->input('case_type');

        $rules = [
            'purchaser' => ['required', 'string', 'max:255'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'bank' => ['nullable', 'string', 'max:255'],
            'property' => ['nullable', 'string', 'max:2000'],
            'title_number' => ['nullable', 'string', 'max:255'],
            'suite_number' => ['nullable', 'string', 'max:255'],
            'ic_number' => ['nullable', 'string', 'max:50'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'person_in_charge_id' => ['nullable', 'exists:staff,id'],
            'related_file_ids' => ['nullable', 'array'],
            'related_file_ids.*' => [
                'integer',
                Rule::exists('legal_files', 'id'),
                Rule::notIn([$file?->id]),
            ],
        ];

        if (! $file?->exists) {
            $rules['case_type'] = ['required', Rule::in(LegalFile::caseTypes())];
            $rules['case_category_id'] = [
                'required',
                Rule::exists('case_categories', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ];
        }

        if ($caseType === LegalFile::TYPE_LOAN) {
            $rules['bank'] = ['required', 'string', 'max:255'];
            $rules['property'] = ['required', 'string', 'max:2000'];
            $rules['title_number'] = ['required', 'string', 'max:255'];
        } elseif ($caseType === LegalFile::TYPE_LITIGATION) {
            $rules['suite_number'] = ['required', 'string', 'max:255'];
            $rules['ic_number'] = ['required', 'string', 'max:50'];
        } else {
            // S&P (default shape)
            $rules['property'] = ['required', 'string', 'max:2000'];
            $rules['title_number'] = ['required', 'string', 'max:255'];
        }

        $data = $request->validate($rules);

        return $this->normalizeCaseTypeFields($data, $caseType);
    }

    /**
     * Drop fields that do not belong to the selected case type so leftover
     * POST values from a previous type selection cannot pollute the record.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeCaseTypeFields(array $data, string $caseType): array
    {
        $data['case_type'] = $caseType;
        $data['vendor'] = LegalFile::isSnpType($caseType) ? ($data['vendor'] ?? null) : null;
        $data['bank'] = $caseType === LegalFile::TYPE_LOAN ? ($data['bank'] ?? null) : null;
        $data['title_number'] = ($caseType === LegalFile::TYPE_LOAN || LegalFile::isSnpType($caseType))
            ? ($data['title_number'] ?? null)
            : null;
        $data['suite_number'] = $caseType === LegalFile::TYPE_LITIGATION ? ($data['suite_number'] ?? null) : null;
        $data['ic_number'] = $caseType === LegalFile::TYPE_LITIGATION ? ($data['ic_number'] ?? null) : null;

        if ($caseType === LegalFile::TYPE_LITIGATION) {
            $data['property'] = '';
        } else {
            $data['property'] = $data['property'] ?? '';
        }

        return $data;
    }

    private function formData(LegalFile $file): array
    {
        $selectedRelatedIds = collect(old('related_file_ids', $file->exists
            ? $file->relatedFiles()->pluck('id')->all()
            : []))->map(fn ($id) => (int) $id)->filter()->values()->all();

        $linkableFiles = LegalFile::query()
            ->when($file->exists, fn ($query) => $query->whereKeyNot($file->id))
            ->orderBy('reference_number')
            ->limit(500)
            ->get(['id', 'reference_number', 'purchaser']);

        $selectedRelatedFiles = $selectedRelatedIds === []
            ? collect()
            : LegalFile::query()
                ->whereIn('id', $selectedRelatedIds)
                ->orderBy('reference_number')
                ->get(['id', 'reference_number', 'purchaser']);

        return [
            'file' => $file,
            'staff' => Staff::where('is_active', true)->orderBy('full_name')->get(),
            'categories' => CaseCategory::query()->where('is_active', true)->orderBy('code')->get(),
            'linkableFiles' => $linkableFiles,
            'linkableFilesJson' => $linkableFiles
                ->map(fn (LegalFile $item): array => [
                    'id' => $item->id,
                    'reference_number' => $item->reference_number,
                    'purchaser' => $item->purchaser,
                ])
                ->values()
                ->all(),
            'selectedRelatedFiles' => $selectedRelatedFiles,
            'selectedRelatedIds' => $selectedRelatedIds,
        ];
    }

    /**
     * @return array{file: LegalFile, qr: string, type: string, types: list<string>}
     */
    private function labelViewData(Request $request, LegalFile $file): array
    {
        $type = $request->string('type')->toString() ?: 'front';
        abort_unless(in_array($type, ['front', 'side', 'both'], true), 404);

        return [
            'file' => $file,
            'qr' => LegalFileQr::dataUri($file),
            'type' => $type,
            'types' => $type === 'both' ? ['front', 'side'] : [$type],
        ];
    }
}
