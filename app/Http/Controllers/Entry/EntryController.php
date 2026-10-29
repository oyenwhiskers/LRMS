<?php

namespace App\Http\Controllers\Entry;

use App\Domain\Entry\RegisterEntryCase;
use App\Domain\Entry\SetEntryScanReviewStatus;
use App\Domain\Entry\StoreEntryCaseOnShelf;
use App\Domain\Entry\UpdateEntryCase;
use App\Http\Controllers\Controller;
use App\Models\CaseCategory;
use App\Models\EntryPrintJob;
use App\Models\EntryScan;
use App\Models\LegalFile;
use App\Models\Rack;
use App\Models\Shelf;
use App\Services\GeminiCaseDraftService;
use App\Support\LegalFileQr;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class EntryController extends Controller
{
    public function home(Request $request): View
    {
        $user = $request->user();
        $savedStatuses = [EntryScan::STATUS_SAVED, EntryScan::STATUS_STORED];
        $scanWith = [
            'legalFile:id,reference_number,case_type,case_category_id,purchaser,vendor,property,bank,title_number,suite_number,ic_number,start_date,end_date,file_identifier,qr_identifier',
            'legalFile.parties',
            'shelf.rack',
        ];

        $storedCount = EntryScan::query()
            ->where('user_id', $user->id)
            ->whereIn('status', $savedStatuses)
            ->count();

        $needsCorrection = EntryScan::query()
            ->where('user_id', $user->id)
            ->whereIn('status', $savedStatuses)
            ->where('review_status', EntryScan::REVIEW_NEEDS_CORRECTION)
            ->with($scanWith)
            ->latest('reviewed_at')
            ->latest()
            ->get();

        $recent = EntryScan::query()
            ->where('user_id', $user->id)
            ->whereIn('status', $savedStatuses)
            ->where('review_status', '!=', EntryScan::REVIEW_NEEDS_CORRECTION)
            ->with($scanWith)
            ->latest()
            ->limit(10)
            ->get();

        $labelQrs = $this->labelQrsForScans($needsCorrection->concat($recent));
        $categories = $this->activeCategories();

        return view('entry.home', compact('storedCount', 'needsCorrection', 'recent', 'labelQrs', 'categories'));
    }

    public function history(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'rack_id' => ['nullable', 'integer', 'exists:racks,id'],
            'shelf_id' => ['nullable', 'integer', 'exists:shelves,id'],
        ]);

        $query = EntryScan::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('status', [EntryScan::STATUS_SAVED, EntryScan::STATUS_STORED])
            ->with([
                'legalFile:id,reference_number,case_type,case_category_id,purchaser,vendor,property,bank,title_number,suite_number,ic_number,start_date,end_date,file_identifier,qr_identifier,status',
                'legalFile.parties',
                'shelf.rack',
            ]);

        if ($search = trim((string) ($filters['q'] ?? ''))) {
            $contains = '%'.addcslashes($search, '\\%_').'%';
            $query->whereHas('legalFile', function ($builder) use ($contains): void {
                $builder->where('reference_number', 'like', $contains)
                    ->orWhere('purchaser', 'like', $contains);
            });
        }

        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['date_from'], config('app.timezone'))->startOfDay());
        }

        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['date_to'], config('app.timezone'))->endOfDay());
        }

        if (! empty($filters['shelf_id'])) {
            $query->where('shelf_id', $filters['shelf_id']);
        } elseif (! empty($filters['rack_id'])) {
            $query->whereHas('shelf', fn ($builder) => $builder->where('rack_id', $filters['rack_id']));
        }

        $scans = $query->latest()->paginate(20)->withQueryString();

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

        return view('entry.history', [
            'scans' => $scans,
            'racks' => $racks,
            'shelves' => $shelves,
            'labelQrs' => $this->labelQrsForScans($scans),
            'categories' => $this->activeCategories(),
            'filters' => [
                'q' => $filters['q'] ?? '',
                'date_from' => $filters['date_from'] ?? '',
                'date_to' => $filters['date_to'] ?? '',
                'rack_id' => $filters['rack_id'] ?? '',
                'shelf_id' => $filters['shelf_id'] ?? '',
            ],
        ]);
    }

    public function label(Request $request, LegalFile $file): View
    {
        $this->authorizeEntryFile($request, $file);

        return view('files.labels.print', $this->labelViewData($request, $file));
    }

    public function labelPdf(Request $request, LegalFile $file): Response
    {
        $this->authorizeEntryFile($request, $file);

        $data = $this->labelViewData($request, $file) + ['pdf' => true];

        return Pdf::loadView('files.labels.print', $data)
            ->setPaper($data['type'] === 'side' ? [0, 0, 226.77, 113.39] : [0, 0, 425.20, 283.46])
            ->download($file->file_identifier.'-'.$data['type'].'-label.pdf');
    }

    public function create(): View
    {
        return view('entry.scan', [
            'categories' => CaseCategory::query()->where('is_active', true)->orderBy('code')->get(),
            'saveUrl' => route('entry.save'),
        ]);
    }

    public function store(Request $request, GeminiCaseDraftService $gemini): JsonResponse
    {
        $data = $request->validate([
            'documents' => ['required', 'array', 'min:1'],
            'documents.*' => ['required', 'file', 'image', 'max:10240'],
        ]);

        // Temporary images only — no EntryScan / draft row until user confirms save.
        $stored = [];
        foreach ($data['documents'] as $file) {
            $path = $file->store('entry-scans/tmp/'.$request->user()->id, 'local');
            $stored[] = [
                'path' => $path,
                'absolute' => Storage::disk('local')->path($path),
                'mime' => $file->getMimeType() ?: 'image/jpeg',
            ];
        }

        $result = $gemini->draftFromImages(
            array_map(
                fn (array $row): array => ['path' => $row['absolute'], 'mime' => $row['mime']],
                $stored,
            ),
            $request->user()->id,
        );

        $tempPaths = array_column($stored, 'path');

        return response()->json([
            'temp_path' => $tempPaths[0],
            'temp_paths' => $tempPaths,
            'pages' => count($tempPaths),
            'draft' => $result['draft'],
            'warning' => $result['error'],
            'save_url' => route('entry.save'),
        ]);
    }

    public function save(Request $request, RegisterEntryCase $register): RedirectResponse
    {
        $tempPaths = $this->validatedTempPaths($request);
        $data = $this->validatedCase($request);

        $scan = EntryScan::create([
            'user_id' => $request->user()->id,
            'document_path' => $tempPaths[0],
            'ai_suggestion' => [
                'reference_number' => $data['reference_number'],
                'case_type' => $data['case_type'],
                'case_category_id' => $data['case_category_id'],
            ],
            'status' => EntryScan::STATUS_DRAFT,
        ]);

        $this->persistScanDocuments($scan, $tempPaths);
        $register->handle($scan, $request->user(), $data);

        return redirect()
            ->route('entry.shelf', $scan)
            ->with('success', 'Kes disimpan. Imbas QR rak tempat fail diletakkan.');
    }

    public function update(
        Request $request,
        EntryScan $scan,
        UpdateEntryCase $updater,
        SetEntryScanReviewStatus $reviewSetter,
    ): RedirectResponse {
        $this->authorizeScan($request, $scan);
        abort_unless(in_array($scan->status, [EntryScan::STATUS_SAVED, EntryScan::STATUS_STORED], true), 422);

        $scan->load('legalFile');
        $file = $scan->legalFile;
        abort_unless($file !== null, 422);

        $data = $this->validatedCase($request, $file);
        $updater->handle($scan, $request->user(), $data);

        if ($scan->review_status === EntryScan::REVIEW_NEEDS_CORRECTION) {
            $reviewSetter->handle($scan, $request->user(), EntryScan::REVIEW_PENDING);
        }

        return back()
            ->with('success', 'Maklumat fail dikemas kini.')
            ->with('open_file_label', $file->id);
    }

    public function document(Request $request, EntryScan $scan): Response
    {
        $this->authorizeScan($request, $scan);

        $paths = $scan->documentPaths();
        $page = max(1, (int) $request->query('page', 1));
        $path = $paths[$page - 1] ?? null;
        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    public function shelf(Request $request, EntryScan $scan): View
    {
        $this->authorizeScan($request, $scan);
        abort_unless($scan->status === EntryScan::STATUS_SAVED, 422, 'Save the case before scanning a shelf.');
        $scan->load(['legalFile']);

        $racks = Rack::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        $shelves = Shelf::query()
            ->where('is_active', true)
            ->with('rack:id,code')
            ->orderBy('code')
            ->get(['id', 'rack_id', 'code', 'name']);

        return view('entry.shelf', compact('scan', 'racks', 'shelves'));
    }

    public function storeShelf(Request $request, EntryScan $scan, StoreEntryCaseOnShelf $store): RedirectResponse
    {
        $this->authorizeScan($request, $scan);

        $data = $request->validate([
            'shelf_qr' => ['nullable', 'string'],
            'shelf_id' => ['nullable', 'integer', 'exists:shelves,id'],
        ]);

        if (empty($data['shelf_qr']) && empty($data['shelf_id'])) {
            throw ValidationException::withMessages([
                'shelf_qr' => 'Imbas QR rak atau pilih rak.',
            ]);
        }

        $shelfId = ! empty($data['shelf_id']) ? (int) $data['shelf_id'] : null;

        $store->handle(
            $scan,
            $request->user(),
            $shelfId ? null : ($data['shelf_qr'] ?? null),
            $shelfId,
        );

        return redirect()
            ->route('entry.home')
            ->with('success', 'Fail disimpan di rak.');
    }

    public function destroy(Request $request, EntryScan $scan): RedirectResponse
    {
        $this->authorizeScan($request, $scan);
        abort_unless(in_array($scan->status, [EntryScan::STATUS_SAVED, EntryScan::STATUS_STORED], true), 422);

        $scan->load('legalFile');
        $file = $scan->legalFile;
        abort_unless($file !== null, 422);
        abort_unless(in_array($file->status, [LegalFile::STATUS_REGISTERED, LegalFile::STATUS_STORED], true), 422);

        $reference = $file->reference_number;
        $documentDir = $this->scanDocumentDirectory($scan);

        DB::transaction(function () use ($scan, $file): void {
            DB::table('legal_file_links')
                ->where(function ($query) use ($file): void {
                    $query->where('legal_file_id', $file->id)
                        ->orWhere('related_legal_file_id', $file->id);
                })
                ->delete();

            $file->movements()->delete();
            $scan->delete();
            $file->delete();
        });

        if ($documentDir !== null) {
            Storage::disk('local')->deleteDirectory($documentDir);
        }

        return redirect()
            ->route('entry.home')
            ->with('success', "Fail {$reference} dipadam.");
    }

    public function lookupShelf(Request $request, string $identifier): JsonResponse
    {
        $shelf = Shelf::with('rack')->where('qr_identifier', $identifier)->firstOrFail();
        abort_unless($shelf->is_active && $shelf->rack?->is_active, 404);

        return response()->json(['location' => $shelf->rack->code.'/'.$shelf->code]);
    }

    private function authorizeScan(Request $request, EntryScan $scan): void
    {
        $user = $request->user();
        abort_unless($scan->isOwnedBy($user) || $user->isAdmin(), 403);
    }

    /**
     * @param  list<string>  $tempPaths
     */
    private function persistScanDocuments(EntryScan $scan, array $tempPaths): void
    {
        $saved = [];
        foreach (array_values($tempPaths) as $index => $tempPath) {
            $extension = pathinfo($tempPath, PATHINFO_EXTENSION) ?: 'jpg';
            $destination = 'entry-scans/'.$scan->id.'/page-'.($index + 1).'.'.$extension;
            Storage::disk('local')->move($tempPath, $destination);
            $saved[] = $destination;
        }

        $scan->update(['document_path' => $saved[0]]);
    }

    private function scanDocumentDirectory(EntryScan $scan): ?string
    {
        $primary = is_string($scan->document_path) ? trim($scan->document_path) : '';
        if ($primary === '') {
            return null;
        }

        $dir = dirname($primary);

        return ($dir === '.' || $dir === '' || $dir === DIRECTORY_SEPARATOR) ? null : $dir;
    }

    private function authorizeEntryFile(Request $request, LegalFile $file): void
    {
        $user = $request->user();
        $owns = EntryScan::query()
            ->where('user_id', $user->id)
            ->where('legal_file_id', $file->id)
            ->whereIn('status', [EntryScan::STATUS_SAVED, EntryScan::STATUS_STORED])
            ->exists();

        // Station desk is not the scan owner; allow any job still listed there.
        $onStation = EntryPrintJob::query()
            ->where('legal_file_id', $file->id)
            ->whereIn('status', [EntryPrintJob::STATUS_PENDING, EntryPrintJob::STATUS_PRINTED])
            ->exists();

        abort_unless($owns || $onStation || $user->isAdmin(), 403);
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
            'embed' => $request->boolean('embed'),
        ];
    }

    /**
     * @param  iterable<int, EntryScan>  $scans
     * @return array<int, string>
     */
    private function labelQrsForScans(iterable $scans): array
    {
        $qrs = [];

        foreach ($scans as $scan) {
            $file = $scan->legalFile;
            if ($file && ! isset($qrs[$file->id])) {
                $qrs[$file->id] = LegalFileQr::dataUri($file);
            }
        }

        return $qrs;
    }

    /**
     * @return list<string>
     */
    private function validatedTempPaths(Request $request): array
    {
        $prefix = 'entry-scans/tmp/'.$request->user()->id.'/';
        $raw = $request->input('temp_paths', $request->input('temp_path'));
        $candidates = is_array($raw) ? $raw : [$raw];

        $paths = [];
        foreach ($candidates as $candidate) {
            $path = is_string($candidate) ? trim($candidate) : '';
            if ($path === '' || ! str_starts_with($path, $prefix) || str_contains($path, '..')) {
                throw ValidationException::withMessages([
                    'temp_path' => 'Sesi gambar tidak sah. Sila imbas semula.',
                ]);
            }
            if (! Storage::disk('local')->exists($path)) {
                throw ValidationException::withMessages([
                    'temp_path' => 'Gambar tidak dijumpai. Sila imbas semula.',
                ]);
            }
            $paths[] = $path;
        }

        $paths = array_values(array_unique($paths));
        if ($paths === [] || count($paths) > 3) {
            throw ValidationException::withMessages([
                'temp_path' => 'Sesi gambar tidak sah. Sila imbas semula.',
            ]);
        }

        return $paths;
    }

    /**
     * @return \Illuminate\Support\Collection<int, CaseCategory>
     */
    private function activeCategories()
    {
        return CaseCategory::query()->where('is_active', true)->orderBy('code')->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedCase(Request $request, ?LegalFile $file = null): array
    {
        $caseType = LegalFile::normalizeCaseTypeCode((string) $request->input('case_type'));
        $request->merge([
            'case_type' => $caseType,
            'reference_number' => preg_replace('/\s+/', '', trim((string) $request->input('reference_number', ''))) ?: '',
        ]);

        $referenceRule = Rule::unique('legal_files', 'reference_number');
        if ($file !== null) {
            $referenceRule = $referenceRule->ignore($file->id);
        }

        // Entry from cover scan: only ref + type + category required. Other fields
        // follow the document; missing ones stay empty for later completion.
        $data = $request->validate([
            'reference_number' => ['required', 'string', 'max:100', $referenceRule],
            'case_type' => ['required', Rule::in(LegalFile::caseTypes())],
            'case_category_id' => [
                'required',
                Rule::exists('case_categories', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'purchaser' => ['nullable', 'string', 'max:255'],
            'borrowers' => ['nullable', 'array'],
            'borrowers.*.name' => ['nullable', 'string', 'max:255'],
            'borrowers.*.ic_number' => ['nullable', 'string', 'max:50'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'bank' => ['nullable', 'string', 'max:255'],
            'property' => ['nullable', 'string', 'max:2000'],
            'title_number' => ['nullable', 'string', 'max:255'],
            'suite_number' => ['nullable', 'string', 'max:255'],
            'ic_number' => ['nullable', 'string', 'max:50'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);
        $data['case_type'] = $caseType;
        $data['borrowers'] = $this->normalizedBorrowers(
            $caseType === LegalFile::TYPE_LOAN ? ($data['borrowers'] ?? []) : [],
            trim((string) ($data['purchaser'] ?? '')),
        );
        $data['purchaser'] = $data['borrowers'] !== []
            ? LegalFile::joinPartyNames(array_column($data['borrowers'], 'name'))
            : trim((string) ($data['purchaser'] ?? ''));
        $data['vendor'] = LegalFile::isSnpType($caseType) ? ($data['vendor'] ?? null) : null;
        $data['bank'] = $caseType === LegalFile::TYPE_LOAN ? ($data['bank'] ?? null) : null;
        $data['title_number'] = ($caseType === LegalFile::TYPE_LOAN || LegalFile::isSnpType($caseType))
            ? ($data['title_number'] ?? null)
            : null;
        $data['suite_number'] = $caseType === LegalFile::TYPE_LITIGATION ? ($data['suite_number'] ?? null) : null;
        $data['ic_number'] = $caseType === LegalFile::TYPE_LITIGATION ? ($data['ic_number'] ?? null) : null;
        $data['property'] = $caseType === LegalFile::TYPE_LITIGATION ? '' : ($data['property'] ?? '');

        return $data;
    }

    /**
     * @param  array<int, array<string, mixed>>  $borrowers
     * @return list<array{name: string, ic_number: ?string}>
     */
    private function normalizedBorrowers(array $borrowers, string $fallbackPurchaser): array
    {
        $rows = [];
        foreach ($borrowers as $borrower) {
            if (! is_array($borrower)) {
                continue;
            }
            $name = trim((string) ($borrower['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $ic = trim((string) ($borrower['ic_number'] ?? ''));
            $rows[] = [
                'name' => $name,
                'ic_number' => $ic !== '' ? $ic : null,
            ];
        }

        if ($rows === [] && $fallbackPurchaser !== '') {
            $rows[] = ['name' => $fallbackPurchaser, 'ic_number' => null];
        }

        return $rows;
    }
}
