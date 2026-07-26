<?php

namespace App\Http\Controllers;

use App\Models\FileMovement;
use App\Models\LegalFile;
use App\Models\Shelf;
use App\Models\Staff;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class LegalFileController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('files.view'), 403);
        $query = LegalFile::query()->with(['currentHolder', 'shelf.cabinet.room'])->active();
        if ($search = trim((string) $request->input('search'))) {
            $escapedSearch = addcslashes($search, '\\%_');
            $containsSearch = "%{$escapedSearch}%";

            if (DB::connection()->getDriverName() === 'mysql' && mb_strlen($search) >= 3) {
                $query->where(function ($q) use ($search, $escapedSearch, $containsSearch): void {
                    $q->where('reference_number', 'like', "{$escapedSearch}%")
                        ->orWhere('loan_reference', 'like', "{$escapedSearch}%")
                        ->orWhereFullText(
                            ['reference_number', 'loan_reference', 'purchaser', 'vendor', 'property'],
                            $search,
                        )
                        ->orWhereHas(
                            'currentHolder',
                            fn ($holder) => $holder->where('full_name', 'like', $containsSearch),
                        );
                });
            } else {
                $query->where(function ($q) use ($containsSearch): void {
                    $q->where('reference_number', 'like', $containsSearch)
                        ->orWhere('loan_reference', 'like', $containsSearch)
                        ->orWhere('purchaser', 'like', $containsSearch)
                        ->orWhere('vendor', 'like', $containsSearch)
                        ->orWhere('property', 'like', $containsSearch)
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

        return view('files.index', ['files' => $query->latest()->paginate(20)->withQueryString()]);
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
        $data['created_by'] = $data['updated_by'] = $request->user()->id;
        $file = LegalFile::create($data);

        return redirect()->route('files.show', $file)->with('success', 'File registered and identity generated.');
    }

    public function show(Request $request, LegalFile $file): View
    {
        abort_unless($request->user()->can('files.view'), 403);
        $file->load(['shelf.cabinet.room', 'currentHolder', 'personInCharge']);
        $movements = $file->movements()
            ->with([
                'employee:id,full_name',
                'previousHolder:id,full_name',
                'processor:id,name',
            ])
            ->paginate(30)
            ->withQueryString();
        $qr = $request->user()->can('labels.print') ? $this->qr($file) : null;

        return view('files.show', compact('file', 'movements', 'qr'));
    }

    public function edit(Request $request, LegalFile $file): View
    {
        abort_unless($request->user()->can('files.update'), 403);

        return view('files.form', $this->formData($file));
    }

    public function update(Request $request, LegalFile $file): RedirectResponse
    {
        abort_unless($request->user()->can('files.update'), 403);
        $file->update($this->validated($request, $file) + ['updated_by' => $request->user()->id]);

        return redirect()->route('files.show', $file)->with('success', 'File record updated.');
    }

    public function archive(Request $request, LegalFile $file): RedirectResponse
    {
        abort_unless($request->user()->can('files.archive'), 403);
        if ($file->status === LegalFile::STATUS_BORROWED) {
            return back()->withErrors(['file' => 'A borrowed file must be returned before it can be archived.']);
        }
        $file->update(['archived_at' => now(), 'updated_by' => $request->user()->id]);

        return redirect()->route('files.index')->with('success', 'File archived.');
    }

    public function label(Request $request, LegalFile $file): View
    {
        abort_unless($request->user()->can('labels.print'), 403);

        return view('files.label', ['file' => $file, 'qr' => $this->qr($file)]);
    }

    public function labelPdf(Request $request, LegalFile $file): Response
    {
        abort_unless($request->user()->can('labels.print'), 403);

        return Pdf::loadView('files.label', ['file' => $file, 'qr' => $this->qr($file), 'pdf' => true])
            ->setPaper('a5')->download($file->file_identifier.'-label.pdf');
    }

    public function lookup(Request $request, string $identifier): JsonResponse
    {
        abort_unless($request->user()->can('movements.borrow') || $request->user()->can('movements.return'), 403);
        $file = LegalFile::with(['currentHolder', 'shelf.cabinet.room'])->where('qr_identifier', $identifier)->active()->firstOrFail();

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
        $data = $request->validate(['notes' => ['required', 'string', 'max:1000']]);
        $this->changeStatus($file, LegalFile::STATUS_MISSING, 'missing', $data['notes'], $request);

        return back()->with('success', 'File marked missing.');
    }

    public function found(Request $request, LegalFile $file): RedirectResponse
    {
        abort_unless($request->user()->can('missing.manage'), 403);
        abort_unless($file->status === LegalFile::STATUS_MISSING, 422, 'Only missing files can be marked found.');
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);
        $this->changeStatus($file, LegalFile::STATUS_AVAILABLE, 'found', $data['notes'] ?? null, $request);

        return back()->with('success', 'File marked found and available.');
    }

    private function changeStatus(LegalFile $file, string $status, string $type, ?string $notes, Request $request): void
    {
        DB::transaction(function () use ($file, $status, $type, $notes, $request): void {
            $locked = LegalFile::lockForUpdate()->findOrFail($file->id);
            FileMovement::create([
                'legal_file_id' => $locked->id,
                'type' => $type,
                'employee_id' => $locked->current_holder_id,
                'previous_holder_id' => $locked->current_holder_id,
                'processed_by' => $request->user()->id,
                'shelf_id' => $locked->shelf_id,
                'previous_status' => $locked->status,
                'new_status' => $status,
                'notes' => $notes,
                'occurred_at' => now(),
            ]);
            $locked->update(['status' => $status, 'current_holder_id' => null, 'updated_by' => $request->user()->id]);
        });
    }

    private function validated(Request $request, ?LegalFile $file = null): array
    {
        $data = $request->validate([
            'reference_number' => ['required', 'string', 'max:100', Rule::unique('legal_files')->ignore($file)],
            'loan_reference' => ['nullable', 'string', 'max:100'],
            'purchaser' => ['required', 'string', 'max:255'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'property' => ['required', 'string', 'max:2000'],
            'matter_type' => ['nullable', 'string', 'max:100'],
            'important_date' => ['nullable', 'date'],
            'person_in_charge_id' => ['nullable', 'exists:staff,id'],
            'shelf_id' => ['required', 'exists:shelves,id'],
        ]);
        $shelf = Shelf::with('cabinet.room')->findOrFail($data['shelf_id']);
        abort_unless($shelf->is_active && $shelf->cabinet->is_active && $shelf->cabinet->room->is_active, 422, 'Select an active storage location.');

        return $data;
    }

    private function formData(LegalFile $file): array
    {
        return [
            'file' => $file,
            'staff' => Staff::where('is_active', true)->orderBy('full_name')->get(),
            'shelves' => Shelf::with('cabinet.room')->where('is_active', true)->orderBy('code')->get(),
        ];
    }

    private function qr(LegalFile $file): string
    {
        return (new SvgWriter)->write(new QrCode(data: $file->qr_identifier))->getDataUri();
    }
}
