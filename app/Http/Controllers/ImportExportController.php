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
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
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
            $locations = $this->locationMap();
            $firstIdentifier = LegalFile::reserveIdentifierRange(count($rows));
            $now = now();
            $records = collect($rows)->values()->map(
                function (array $row, int $index) use ($locations, $firstIdentifier, $now, $request): array {
                    $shelfId = $locations->get($this->locationKey($row));

                    return [
                        'file_identifier' => LegalFile::formatIdentifier($firstIdentifier + $index),
                        'qr_identifier' => (string) Str::uuid(),
                        'reference_number' => $row['reference_number'],
                        'loan_reference' => $row['loan_reference'] ?: null,
                        'purchaser' => $row['purchaser'],
                        'vendor' => $row['vendor'] ?: null,
                        'property' => $row['property'],
                        'matter_type' => $row['matter_type'] ?: null,
                        'shelf_id' => $shelfId,
                        'created_by' => $request->user()->id,
                        'updated_by' => $request->user()->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            );

            DB::transaction(function () use ($records): void {
                $records->chunk(500)->each(
                    fn (Collection $chunk) => DB::table('legal_files')->insert($chunk->all())
                );
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

        return Excel::download(new class implements FromQuery, WithHeadings, WithMapping
        {
            public function query()
            {
                return LegalFile::query()
                    ->leftJoin('shelves', 'legal_files.shelf_id', '=', 'shelves.id')
                    ->leftJoin('cabinets', 'shelves.cabinet_id', '=', 'cabinets.id')
                    ->leftJoin('rooms', 'cabinets.room_id', '=', 'rooms.id')
                    ->leftJoin('staff as current_holders', 'legal_files.current_holder_id', '=', 'current_holders.id')
                    ->select([
                        'legal_files.file_identifier',
                        'legal_files.reference_number',
                        'legal_files.loan_reference',
                        'legal_files.purchaser',
                        'legal_files.vendor',
                        'legal_files.property',
                        'legal_files.status',
                        'current_holders.full_name as current_holder_name',
                        'rooms.code as room_code',
                        'cabinets.code as cabinet_code',
                        'shelves.code as shelf_code',
                    ])
                    ->orderBy('legal_files.id');
            }

            public function map($file): array
            {
                return [
                    $file->file_identifier,
                    $file->reference_number,
                    $file->loan_reference,
                    $file->purchaser,
                    $file->vendor,
                    $file->property,
                    $file->status,
                    $file->current_holder_name,
                    $file->room_code,
                    $file->cabinet_code,
                    $file->shelf_code,
                ];
            }

            public function headings(): array
            {
                return [
                    'File ID', 'Reference Number', 'Loan Reference', 'Purchaser', 'Vendor',
                    'Property', 'Status', 'Current Holder', 'Room', 'Cabinet', 'Shelf',
                ];
            }
        }, 'lrms-files-'.now()->format('Ymd').'.xlsx');
    }

    public function movements(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->can('exports.movements'), 403);

        return Excel::download(new class implements FromQuery, WithHeadings, WithMapping
        {
            public function query()
            {
                return FileMovement::query()
                    ->leftJoin('legal_files', 'file_movements.legal_file_id', '=', 'legal_files.id')
                    ->leftJoin('staff as employees', 'file_movements.employee_id', '=', 'employees.id')
                    ->leftJoin('staff as previous_holders', 'file_movements.previous_holder_id', '=', 'previous_holders.id')
                    ->leftJoin('users as processors', 'file_movements.processed_by', '=', 'processors.id')
                    ->select([
                        'legal_files.reference_number',
                        'file_movements.type',
                        'file_movements.previous_status',
                        'file_movements.new_status',
                        'previous_holders.full_name as previous_holder_name',
                        'employees.full_name as employee_name',
                        'processors.name as processor_name',
                        'file_movements.occurred_at',
                        'file_movements.notes',
                    ])
                    ->orderByDesc('file_movements.occurred_at');
            }

            public function map($movement): array
            {
                return [
                    $movement->reference_number,
                    $movement->type,
                    $movement->previous_status,
                    $movement->new_status,
                    $movement->previous_holder_name,
                    $movement->employee_name,
                    $movement->processor_name,
                    $movement->occurred_at?->toDateTimeString(),
                    $movement->notes,
                ];
            }

            public function headings(): array
            {
                return [
                    'Reference Number', 'Movement', 'Previous Status', 'New Status',
                    'Previous Holder', 'Employee/Returner', 'Processed By', 'Occurred At', 'Notes',
                ];
            }
        }, 'lrms-movements-'.now()->format('Ymd').'.xlsx');
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

        $references = collect($rows)->pluck('reference_number')->filter()->unique()->values();
        $existingReferences = LegalFile::query()
            ->whereIn('reference_number', $references)
            ->pluck('reference_number')
            ->flip();
        $locations = $this->locationMap();

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            if (empty($row['reference_number']) || empty($row['purchaser']) || empty($row['property'])) {
                $errors[] = "Row {$line}: reference number, purchaser and property are required.";

                continue;
            }
            if ($existingReferences->has($row['reference_number'])) {
                $errors[] = "Row {$line}: reference number already exists.";
            }
            if (! $locations->has($this->locationKey($row))) {
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

    private function locationMap(): Collection
    {
        return Shelf::query()
            ->join('cabinets', 'shelves.cabinet_id', '=', 'cabinets.id')
            ->join('rooms', 'cabinets.room_id', '=', 'rooms.id')
            ->get([
                'shelves.id',
                'shelves.code as shelf_code',
                'cabinets.code as cabinet_code',
                'rooms.code as room_code',
            ])
            ->mapWithKeys(fn (Shelf $shelf) => [
                $this->locationKey($shelf->getAttributes()) => $shelf->id,
            ]);
    }

    private function locationKey(array $row): string
    {
        return implode('|', [
            mb_strtoupper(trim((string) ($row['room_code'] ?? ''))),
            mb_strtoupper(trim((string) ($row['cabinet_code'] ?? ''))),
            mb_strtoupper(trim((string) ($row['shelf_code'] ?? ''))),
        ]);
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
