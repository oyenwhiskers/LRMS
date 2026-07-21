<?php

namespace App\Http\Controllers;

use App\Models\FileMovement;
use App\Models\ImportRun;
use App\Models\LegalFile;
use App\Models\Shelf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportExportController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('imports.view'), 403);

        return view('imports.index', ['runs' => ImportRun::with('creator')->latest()->paginate(15)]);
    }

    public function preview(Request $request): View
    {
        abort_unless($request->user()->can('imports.create'), 403);
        $request->validate(['workbook' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240']]);
        $token = (string) Str::uuid();
        $extension = $request->file('workbook')->getClientOriginalExtension();
        $path = $request->file('workbook')->storeAs('imports', "{$token}.{$extension}");
        $rows = $this->rows($path);
        $errors = $this->validateRows($rows);
        $request->session()->put('import_preview', [
            'token' => $token, 'path' => $path,
            'name' => $request->file('workbook')->getClientOriginalName(),
        ]);

        return view('imports.index', [
            'runs' => ImportRun::with('creator')->latest()->paginate(15),
            'preview' => collect($rows)->take(20),
            'total' => count($rows),
            'previewErrors' => $errors,
            'token' => $token,
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('imports.create'), 403);
        $data = $request->validate(['token' => ['required', 'uuid']]);
        $preview = $request->session()->get('import_preview');
        abort_unless($preview && hash_equals($preview['token'], $data['token']) && Storage::exists($preview['path']), 419);
        $rows = $this->rows($preview['path']);
        $errors = $this->validateRows($rows);
        $run = ImportRun::create([
            'original_filename' => $preview['name'], 'status' => $errors ? 'failed' : 'processing',
            'total_rows' => count($rows), 'failed_rows' => count($errors),
            'errors' => $errors ?: null, 'created_by' => $request->user()->id,
        ]);
        if ($errors) {
            return redirect()->route('imports.index')->withErrors(['workbook' => 'Import stopped because validation errors were found.']);
        }

        try {
            DB::transaction(function () use ($rows, $request): void {
                foreach ($rows as $row) {
                    $shelf = Shelf::where('code', $row['shelf_code'])
                        ->whereHas('cabinet', fn ($q) => $q->where('code', $row['cabinet_code'])
                            ->whereHas('room', fn ($room) => $room->where('code', $row['room_code'])))->firstOrFail();
                    LegalFile::create([
                        'reference_number' => $row['reference_number'],
                        'loan_reference' => $row['loan_reference'] ?: null,
                        'purchaser' => $row['purchaser'],
                        'vendor' => $row['vendor'] ?: null,
                        'property' => $row['property'],
                        'matter_type' => $row['matter_type'] ?: null,
                        'shelf_id' => $shelf->id,
                        'created_by' => $request->user()->id,
                        'updated_by' => $request->user()->id,
                    ]);
                }
            });
            $run->update(['status' => 'completed', 'imported_rows' => count($rows)]);
        } catch (\Throwable $exception) {
            report($exception);
            $run->update([
                'status' => 'failed',
                'failed_rows' => count($rows),
                'errors' => ['The import was rolled back because an unexpected database error occurred.'],
            ]);

            return redirect()->route('imports.index')->withErrors(['workbook' => 'Import failed safely; no rows were added.']);
        }
        Storage::delete($preview['path']);
        $request->session()->forget('import_preview');

        return redirect()->route('imports.index')->with('success', count($rows).' file records imported.');
    }

    public function template(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->can('imports.view'), 403);

        return Excel::download($this->export(collect(), $this->headings()), 'lrms-import-template.xlsx');
    }

    public function files(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->can('exports.files'), 403);
        $rows = LegalFile::with(['shelf.cabinet.room', 'currentHolder'])->get()->map(fn ($file) => [
            $file->file_identifier, $file->reference_number, $file->loan_reference, $file->purchaser,
            $file->vendor, $file->property, $file->status, $file->currentHolder?->full_name,
            $file->shelf?->cabinet?->room?->code, $file->shelf?->cabinet?->code, $file->shelf?->code,
        ]);

        return Excel::download($this->export($rows, [
            'File ID', 'Reference Number', 'Loan Reference', 'Purchaser', 'Vendor', 'Property',
            'Status', 'Current Holder', 'Room', 'Cabinet', 'Shelf',
        ]), 'lrms-files-'.now()->format('Ymd').'.xlsx');
    }

    public function movements(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->can('exports.movements'), 403);
        $rows = FileMovement::with(['legalFile', 'employee', 'previousHolder', 'processor'])->latest('occurred_at')->get()->map(fn ($movement) => [
            $movement->legalFile?->reference_number, $movement->type, $movement->previous_status,
            $movement->new_status, $movement->previousHolder?->full_name, $movement->employee?->full_name,
            $movement->processor?->name, $movement->occurred_at?->toDateTimeString(), $movement->notes,
        ]);

        return Excel::download($this->export($rows, [
            'Reference Number', 'Movement', 'Previous Status', 'New Status', 'Previous Holder',
            'Employee/Returner', 'Processed By', 'Occurred At', 'Notes',
        ]), 'lrms-movements-'.now()->format('Ymd').'.xlsx');
    }

    public function errors(Request $request, ImportRun $run): StreamedResponse
    {
        abort_unless($request->user()->can('imports.view'), 403);
        abort_unless($run->errors, 404);

        return response()->streamDownload(function () use ($run): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Import validation error']);
            foreach ($run->errors as $error) {
                fputcsv($stream, [$error]);
            }
            fclose($stream);
        }, "lrms-import-{$run->identifier}-errors.csv", ['Content-Type' => 'text/csv']);
    }

    private function rows(string $path): array
    {
        $sheet = IOFactory::load(Storage::path($path))->getActiveSheet()->toArray(null, true, true, false);
        if (count($sheet) < 2) {
            return [];
        }
        $headers = array_map(fn ($value) => Str::snake(trim((string) $value)), array_shift($sheet));

        return array_values(array_filter(array_map(fn ($row) => array_combine($headers, array_pad($row, count($headers), null)), $sheet), fn ($row) => collect($row)->filter(fn ($value) => $value !== null && $value !== '')->isNotEmpty()));
    }

    private function validateRows(array $rows): array
    {
        if ($rows === []) {
            return ['The workbook contains no data rows.'];
        }

        $errors = [];
        $requiredColumns = array_map(fn ($heading) => Str::snake($heading), $this->headings());
        $missingColumns = array_diff($requiredColumns, array_keys($rows[0]));
        if ($missingColumns) {
            return array_map(fn ($column) => 'Missing required column: '.Str::headline($column).'.', $missingColumns);
        }

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            if (empty($row['reference_number']) || empty($row['purchaser']) || empty($row['property'])) {
                $errors[] = "Row {$line}: reference number, purchaser and property are required.";

                continue;
            }
            if (LegalFile::where('reference_number', $row['reference_number'])->exists()) {
                $errors[] = "Row {$line}: reference number already exists.";
            }
            $location = Shelf::where('code', $row['shelf_code'] ?? '')
                ->whereHas('cabinet', fn ($q) => $q->where('code', $row['cabinet_code'] ?? '')
                    ->whereHas('room', fn ($room) => $room->where('code', $row['room_code'] ?? '')))->exists();
            if (! $location) {
                $errors[] = "Row {$line}: storage location was not found.";
            }
        }
        $duplicates = collect($rows)->pluck('reference_number')->filter()->duplicates();
        foreach ($duplicates as $reference) {
            $errors[] = "Duplicate reference number in workbook: {$reference}.";
        }

        return array_values(array_unique($errors));
    }

    private function headings(): array
    {
        return ['Reference Number', 'Loan Reference', 'Purchaser', 'Vendor', 'Property', 'Matter Type', 'Room Code', 'Cabinet Code', 'Shelf Code'];
    }

    private function export(Collection $rows, array $headings): object
    {
        return new class($rows, $headings) implements FromCollection, WithHeadings
        {
            public function __construct(private Collection $rows, private array $columns) {}

            public function collection(): Collection
            {
                return $this->rows;
            }

            public function headings(): array
            {
                return $this->columns;
            }
        };
    }
}
