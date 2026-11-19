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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
                            ['reference_number', 'purchaser', 'vendor', 'agent', 'property'],
                            $search,
                        )
                        ->orWhere('agent', 'like', $containsSearch)
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
                        ->orWhere('agent', 'like', $containsSearch)
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

        $this->applyRegisterDateFilter(
            $query,
            $request->string('period')->value(),
            $request->string('date')->value(),
            $request->string('date_from')->value(),
            $request->string('date_to')->value(),
        );

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
        $stickerParties = is_array($data['sticker_parties'] ?? null) ? $data['sticker_parties'] : null;
        unset($data['related_file_ids'], $data['sticker_parties'], $data['purchasers'], $data['vendors'], $data['agents'], $data['sticker_party']);
        $data['created_by'] = $data['updated_by'] = $request->user()->id;

        $file = DB::transaction(function () use ($data, $relatedIds, $stickerParties, $request): LegalFile {
            $category = CaseCategory::query()
                ->whereKey($data['case_category_id'])
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();

            $data['reference_number'] = $category->nextReference($data['case_type'] ?? null);
            $data['matter_type'] = $category->name;

            $file = LegalFile::create($data);
            $file->syncRelatedFiles($relatedIds);
            if ($stickerParties !== null) {
                $file->syncParties($stickerParties);
            }

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
        $stickerParties = is_array($data['sticker_parties'] ?? null) ? $data['sticker_parties'] : null;
        unset(
            $data['related_file_ids'],
            $data['case_category_id'],
            $data['case_type'],
            $data['sticker_parties'],
            $data['purchasers'],
            $data['vendors'],
            $data['agents'],
            $data['sticker_party'],
        );

        DB::transaction(function () use ($file, $data, $relatedIds, $stickerParties, $request): void {
            $file->update($data + ['updated_by' => $request->user()->id]);
            $file->syncRelatedFiles($relatedIds);
            if ($stickerParties !== null) {
                $file->syncParties($stickerParties);
            }
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

    private function applyRegisterDateFilter(
        $query,
        string $period,
        string $selectedDate,
        string $dateFrom,
        string $dateTo,
    ): void {
        match ($period) {
            'today' => $query->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()]),
            'this_week' => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
            'this_month' => $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]),
            'specific_date' => $this->applySpecificRegisterDate($query, $selectedDate),
            'custom_range' => $this->applyCustomRegisterDateRange($query, $dateFrom, $dateTo),
            default => null,
        };
    }

    private function applySpecificRegisterDate($query, string $selectedDate): void
    {
        if ($selectedDate === '') {
            return;
        }

        $date = Carbon::parse($selectedDate);
        $query->whereBetween('created_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()]);
    }

    private function applyCustomRegisterDateRange($query, string $dateFrom, string $dateTo): void
    {
        if ($dateFrom !== '') {
            $query->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
        }

        if ($dateTo !== '') {
            $query->where('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
        }
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
            ? LegalFile::normalizeCaseTypeCode((string) $file->case_type)
            : LegalFile::normalizeCaseTypeCode((string) $request->input('case_type'));

        if (! $file?->exists) {
            $request->merge(['case_type' => $caseType]);
        }

        $isSnp = LegalFile::isSnpType($caseType);
        $rules = [
            'purchaser' => [$isSnp ? 'nullable' : 'required', 'string', 'max:255'],
            'purchasers' => ['nullable', 'array'],
            'purchasers.*' => ['nullable', 'string', 'max:255'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'vendors' => ['nullable', 'array'],
            'vendors.*' => ['nullable', 'string', 'max:255'],
            'agent' => ['nullable', 'string', 'max:255'],
            'agents' => ['nullable', 'array'],
            'agents.*' => ['nullable', 'string', 'max:255'],
            'sticker_party' => ['nullable', 'string', 'max:40'],
            'bank' => ['nullable', 'string', 'max:255'],
            'property' => ['nullable', 'string'],
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
            $rules['case_type'] = ['required', Rule::in(LegalFile::manualCaseTypes())];
            $rules['case_category_id'] = [
                'required',
                Rule::exists('case_categories', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ];
            $rules['case_sub_type'] = ['nullable', 'string', Rule::in(LegalFile::manualSubTypes())];
        }

        if (LegalFile::isLoanType($caseType)) {
            $rules['bank'] = ['required', 'string', 'max:255'];
            $rules['property'] = ['required', 'string'];
        } elseif ($caseType === LegalFile::TYPE_LITIGATION) {
            $rules['suite_number'] = ['required', 'string', 'max:255'];
            $rules['ic_number'] = ['required', 'string', 'max:50'];
        } else {
            // S&P (default shape) — title number optional (customers sometimes omit it)
            $rules['property'] = ['required', 'string'];
        }

        $data = $request->validate($rules);

        if (! $file?->exists) {
            $category = CaseCategory::query()->find($data['case_category_id']);
            $categoryCode = strtoupper((string) ($category?->code ?? ''));
            $subType = trim((string) ($data['case_sub_type'] ?? ''));
            $allowedSubTypes = match (true) {
                $categoryCode === 'CON' && $caseType === LegalFile::TYPE_SNP => LegalFile::conSnpSubTypes(),
                $categoryCode === 'CON' && $caseType === LegalFile::TYPE_MOT => LegalFile::conMotSubTypes(),
                default => [],
            };

            // Sub-type is optional even for CON + S&P / MOT; blank means plain type.
            if ($allowedSubTypes === [] || $subType === '') {
                $data['case_sub_type'] = null;
            } elseif (! in_array($subType, $allowedSubTypes, true)) {
                throw ValidationException::withMessages([
                    'case_sub_type' => 'The selected case sub-type is invalid for this category and case type.',
                ]);
            } else {
                $data['case_sub_type'] = $subType;
                if ($caseType === LegalFile::TYPE_SNP) {
                    $caseType = LegalFile::caseTypeFromManualSubType($subType);
                }
            }
        }

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
        $data['bank'] = LegalFile::isLoanType($caseType) ? ($data['bank'] ?? null) : null;
        $data['title_number'] = (LegalFile::isLoanType($caseType) || LegalFile::isSnpType($caseType))
            ? ($data['title_number'] ?? null)
            : null;
        $data['suite_number'] = $caseType === LegalFile::TYPE_LITIGATION ? ($data['suite_number'] ?? null) : null;
        $data['ic_number'] = $caseType === LegalFile::TYPE_LITIGATION ? ($data['ic_number'] ?? null) : null;

        if ($caseType === LegalFile::TYPE_LITIGATION) {
            $data['property'] = '';
        } else {
            $data['property'] = $data['property'] ?? '';
        }

        if (LegalFile::isSnpType($caseType)) {
            [$purchasers, $vendors, $stickerParties] = $this->normalizedSnpParties($data);
            if ($purchasers === []) {
                throw ValidationException::withMessages([
                    'purchasers' => 'At least one purchaser is required.',
                ]);
            }
            $agents = $this->normalizedNameList($data['agents'] ?? null, $data['agent'] ?? null);
            $data['purchasers'] = $purchasers;
            $data['vendors'] = $vendors;
            $data['agents'] = $agents;
            $data['purchaser'] = LegalFile::joinPartyNames($purchasers);
            $data['vendor'] = $vendors === [] ? null : LegalFile::joinPartyNames($vendors);
            $data['agent'] = $agents === [] ? null : LegalFile::joinPartyNames($agents);
            $data['sticker_parties'] = $stickerParties;
        } else {
            $data['vendor'] = null;
            $data['agent'] = null;
            $data['sticker_parties'] = null;
            $data['purchaser'] = trim((string) ($data['purchaser'] ?? ''));
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: list<string>, 1: list<string>, 2: list<array{name: string, ic_number: null, is_primary: bool}>}
     */
    private function normalizedSnpParties(array $data): array
    {
        $purchasers = $this->normalizedNameList($data['purchasers'] ?? null, $data['purchaser'] ?? null);
        $vendors = $this->normalizedNameList($data['vendors'] ?? null, $data['vendor'] ?? null);

        $stickerParty = trim((string) ($data['sticker_party'] ?? ''));
        $primarySource = 'purchaser';
        $primaryIndex = 0;
        if (preg_match('/^(purchaser|vendor):(\d+)$/', $stickerParty, $matches) === 1) {
            $primarySource = $matches[1];
            $primaryIndex = (int) $matches[2];
        }

        $rows = [];
        foreach ($purchasers as $index => $name) {
            $rows[] = [
                'name' => $name,
                'ic_number' => null,
                'is_primary' => $primarySource === 'purchaser' && $index === $primaryIndex,
            ];
        }
        foreach ($vendors as $index => $name) {
            $rows[] = [
                'name' => $name,
                'ic_number' => null,
                'is_primary' => $primarySource === 'vendor' && $index === $primaryIndex,
            ];
        }

        if ($rows !== [] && ! collect($rows)->contains(fn (array $row): bool => $row['is_primary'])) {
            $rows[0]['is_primary'] = true;
        }

        return [$purchasers, $vendors, $rows];
    }

    /**
     * @return list<string>
     */
    private function normalizedNameList(mixed $names, mixed $fallback): array
    {
        if (is_array($names)) {
            return collect($names)
                ->map(fn ($name): string => trim((string) $name))
                ->filter()
                ->values()
                ->all();
        }

        return LegalFile::splitPartyNames(is_string($fallback) ? $fallback : null);
    }

    private function formData(LegalFile $file): array
    {
        $file->loadMissing('parties');

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

        $purchasers = old('purchasers');
        if (! is_array($purchasers)) {
            $purchasers = LegalFile::splitPartyNames(old('purchaser', $file->purchaser));
        }
        $purchasers = array_values(array_map(fn ($name) => trim((string) $name), $purchasers));
        if ($purchasers === []) {
            $purchasers = [''];
        }

        $vendors = old('vendors');
        if (! is_array($vendors)) {
            $vendors = LegalFile::splitPartyNames(old('vendor', $file->vendor));
        }
        $vendors = array_values(array_map(fn ($name) => trim((string) $name), $vendors));
        if ($vendors === []) {
            $vendors = [''];
        }

        $agents = old('agents');
        if (! is_array($agents)) {
            $agents = LegalFile::splitPartyNames(old('agent', $file->agent));
        }
        $agents = array_values(array_map(fn ($name) => trim((string) $name), $agents));
        if ($agents === []) {
            $agents = [''];
        }

        $stickerParty = old('sticker_party');
        if (! is_string($stickerParty) || $stickerParty === '') {
            $stickerParty = $this->stickerPartyValueFromFile($file, $purchasers, $vendors);
        }

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
            'formPurchasers' => $purchasers,
            'formVendors' => $vendors,
            'formAgents' => $agents,
            'formStickerParty' => $stickerParty,
        ];
    }

    /**
     * @param  list<string>  $purchasers
     * @param  list<string>  $vendors
     */
    private function stickerPartyValueFromFile(LegalFile $file, array $purchasers, array $vendors): string
    {
        $primary = $file->parties->firstWhere('is_primary', true) ?? $file->parties->first();
        $primaryName = trim((string) ($primary?->name ?? ''));
        if ($primaryName !== '') {
            $vendorIndex = array_search($primaryName, $vendors, true);
            if ($vendorIndex !== false) {
                return 'vendor:'.$vendorIndex;
            }
            $purchaserIndex = array_search($primaryName, $purchasers, true);
            if ($purchaserIndex !== false) {
                return 'purchaser:'.$purchaserIndex;
            }
        }

        return 'purchaser:0';
    }

    /**
     * @return array{file: LegalFile, qr: string, type: string, types: list<string>}
     */
    private function labelViewData(Request $request, LegalFile $file): array
    {
        $type = $request->string('type')->toString() ?: 'front';
        abort_unless(in_array($type, ['front', 'side', 'both'], true), 404);
        $file->loadMissing('parties');

        return [
            'file' => $file,
            'qr' => LegalFileQr::dataUri($file),
            'type' => $type,
            'types' => $type === 'both' ? ['front', 'side'] : [$type],
        ];
    }
}
