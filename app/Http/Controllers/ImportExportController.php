<?php

namespace App\Http\Controllers;

use App\Models\CaseCategory;
use App\Models\FileMovement;
use App\Models\ImportRun;
use App\Models\LegalFile;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
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
use Symfony\Component\HttpFoundation\Response;
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
            $categories = CaseCategory::query()
                ->where('is_active', true)
                ->get()
                ->keyBy(fn (CaseCategory $category) => strtoupper($category->code));

            $now = now();
            $year = (int) $now->format('Y');

            $records = DB::transaction(function () use ($rows, $categories, $now, $year, $request): Collection {
                $firstIdentifier = LegalFile::reserveIdentifierRange(count($rows));
                $grouped = collect($rows)->values()->groupBy(
                    fn (array $row) => strtoupper(trim((string) $row['case_category_code']))
                );

                $sequenceStarts = [];
                foreach ($grouped as $code => $groupRows) {
                    $category = $categories->get($code);
                    $sequenceStarts[$code] = $category->reserveNextNumber($groupRows->count());
                }

                $categoryOffsets = array_fill_keys(array_keys($sequenceStarts), 0);

                return collect($rows)->values()->map(
                    function (array $row, int $index) use ($firstIdentifier, $categories, $sequenceStarts, &$categoryOffsets, $now, $year, $request): array {
                        $code = strtoupper(trim((string) $row['case_category_code']));
                        $category = $categories->get($code);
                        $number = $sequenceStarts[$code] + $categoryOffsets[$code];
                        $categoryOffsets[$code]++;

                        return [
                            'file_identifier' => LegalFile::formatIdentifier($firstIdentifier + $index),
                            'qr_identifier' => (string) Str::uuid(),
                            'reference_number' => $category->formatReference($number, $year),
                            'purchaser' => $row['purchaser'],
                            'vendor' => $row['vendor'] ?: null,
                            'property' => $row['property'],
                            'case_category_id' => $category->id,
                            'case_type' => LegalFile::TYPE_SNP,
                            'matter_type' => $category->name,
                            'status' => LegalFile::STATUS_REGISTERED,
                            'created_by' => $request->user()->id,
                            'updated_by' => $request->user()->id,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                );
            });

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
                    ->leftJoin('racks', 'shelves.rack_id', '=', 'racks.id')
                    ->leftJoin('case_categories', 'legal_files.case_category_id', '=', 'case_categories.id')
                    ->leftJoin('staff as current_holders', 'legal_files.current_holder_id', '=', 'current_holders.id')
                    ->select([
                        'legal_files.file_identifier',
                        'legal_files.reference_number',
                        'legal_files.purchaser',
                        'legal_files.vendor',
                        'legal_files.property',
                        'case_categories.code as category_code',
                        'case_categories.name as category_name',
                        'legal_files.status',
                        'current_holders.full_name as current_holder_name',
                        'racks.code as rack_code',
                        'shelves.code as shelf_code',
                        'shelves.name as shelf_name',
                    ])
                    ->orderBy('legal_files.id');
            }

            public function map($file): array
            {
                return [
                    $file->file_identifier,
                    $file->reference_number,
                    $file->purchaser,
                    $file->vendor,
                    $file->property,
                    $file->category_code,
                    $file->category_name,
                    $file->status,
                    $file->current_holder_name,
                    $file->rack_code,
                    $file->shelf_code,
                    $file->shelf_name,
                ];
            }

            public function headings(): array
            {
                return [
                    'File ID', 'Reference Number', 'Purchaser', 'Vendor',
                    'Property', 'Category Code', 'Category Name', 'Status', 'Current Holder',
                    'Rack', 'Shelf Code', 'Shelf Name',
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
                    ->leftJoin('staff as receivers', 'file_movements.receiver_id', '=', 'receivers.id')
                    ->leftJoin('staff as previous_holders', 'file_movements.previous_holder_id', '=', 'previous_holders.id')
                    ->leftJoin('users as processors', 'file_movements.processed_by', '=', 'processors.id')
                    ->select([
                        'legal_files.reference_number',
                        'file_movements.type',
                        'file_movements.previous_status',
                        'file_movements.new_status',
                        'previous_holders.full_name as previous_holder_name',
                        'employees.full_name as employee_name',
                        'receivers.full_name as receiver_name',
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
                    $movement->receiver_name,
                    $movement->processor_name,
                    $movement->occurred_at?->toDateTimeString(),
                    $movement->notes,
                ];
            }

            public function headings(): array
            {
                return [
                    'Reference Number', 'Movement', 'Previous Status', 'New Status',
                    'Previous Holder', 'Employee/Returner', 'Receiver PIC', 'Processed By', 'Occurred At', 'Notes',
                ];
            }
        }, 'lrms-movements-'.now()->format('Ymd').'.xlsx');
    }

    public function dashboard(Request $request, string $section): StreamedResponse|BinaryFileResponse|Response
    {
        abort_unless($request->user()->can('dashboard.view'), 403);

        return match ($section) {
            'registration-summary' => $this->dashboardRegistrationExport($request),
            'custody-view' => $this->dashboardCustodyExport($request),
            'registration-trend' => $this->dashboardTrendExport($request),
            default => abort(404),
        };
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

        $activeCodes = CaseCategory::query()
            ->where('is_active', true)
            ->pluck('code')
            ->map(fn (string $code) => strtoupper($code))
            ->all();

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            if (empty($row['purchaser']) || empty($row['property']) || empty($row['case_category_code'])) {
                $errors[] = "Row {$line}: case category code, purchaser and property are required.";

                continue;
            }

            $code = strtoupper(trim((string) $row['case_category_code']));
            if (! in_array($code, $activeCodes, true)) {
                $errors[] = "Row {$line}: case category code {$code} was not found or is inactive.";
            }
        }

        return array_values(array_unique($errors));
    }

    private function headings(): array
    {
        return ['Case Category Code', 'Purchaser', 'Vendor', 'Property'];
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

    private function dashboardRegistrationExport(Request $request): StreamedResponse|BinaryFileResponse|Response
    {
        $scope = $request->string('scope')->value() ?: 'all';
        $format = $this->dashboardExportFormat($request);
        $summary = $this->dashboardRegistrationSummary();

        $rows = collect([
            ['key' => 'today', 'label' => 'Cases registered today', 'value' => $summary['registered_today'], 'context' => 'Added to LRMS'],
            ['key' => 'week', 'label' => 'Cases registered this week', 'value' => $summary['registered_week'], 'context' => 'Current week'],
            ['key' => 'month', 'label' => 'Cases registered this month', 'value' => $summary['registered_month'], 'context' => now()->format('F Y')],
            ['key' => 'year', 'label' => 'Cases registered this year', 'value' => $summary['registered_year'], 'context' => now()->format('Y')],
        ]);

        if ($scope !== 'all') {
            $rows = $rows->where('key', $scope)->values();
        }

        $records = $this->dashboardRegistrationRecords($scope);
        $reportPeriod = match ($scope) {
            'today' => 'Today',
            'week' => 'This week',
            'month' => 'This month',
            'year' => 'This year',
            default => 'Year to date summary',
        };

        if ($format === 'pdf') {
            return $this->dashboardPdf('exports.dashboard-section', [
                'title' => 'Registration summary export',
                'subtitle' => 'Case registration report for '.$reportPeriod.' as of '.now()->format('d M Y, h:i A'),
                'logo' => $this->dashboardLogoDataUri(),
                'summaryTitle' => 'Registration metrics',
                'columns' => ['Metric', 'Value', 'Context'],
                'rows' => $rows->map(fn (array $row) => [$row['label'], (string) $row['value'], $row['context']])->all(),
                'detailTitle' => 'Registered case list',
                'detailColumns' => ['#', 'Reference', 'Purchaser', 'Vendor', 'Property', 'Registered at', 'Current status'],
                'detailRows' => $records->values()->map(fn (LegalFile $file, int $index) => [
                    $index + 1,
                    $file->reference_number,
                    $file->purchaser,
                    $file->vendor ?: '—',
                    $file->property,
                    $file->created_at?->format('d M Y, H:i') ?: '—',
                    $file->statusLabel(),
                ])->all(),
            ])->setPaper('a4', 'landscape')->download('dashboard-registration-summary-'.now()->format('Ymd-His').'.pdf');
        }

        return $this->streamCsvDownload(
            'dashboard-registration-summary-'.now()->format('Ymd-His').'.csv',
            ['Metric', 'Value', 'Context'],
            $rows->map(fn (array $row) => [$row['label'], $row['value'], $row['context']])
        );
    }

    private function dashboardCustodyExport(Request $request): StreamedResponse|BinaryFileResponse|Response
    {
        $scope = $request->string('scope')->value() ?: 'all';
        $format = $this->dashboardExportFormat($request);
        $query = LegalFile::active()
            ->with(['currentHolder:id,full_name', 'shelf.rack'])
            ->orderBy('reference_number');

        $statusMap = [
            'registered' => LegalFile::STATUS_REGISTERED,
            'pending' => LegalFile::STATUS_PENDING,
            'completed' => LegalFile::STATUS_COMPLETED,
            'stored' => LegalFile::STATUS_STORED,
            'borrowed' => LegalFile::STATUS_BORROWED,
            'missing' => LegalFile::STATUS_MISSING,
        ];

        if (isset($statusMap[$scope])) {
            $query->where('status', $statusMap[$scope]);
        }

        $files = $query->get();

        $rows = $files->values()->map(function (LegalFile $file, int $index): array {
            return [
                $index + 1,
                $file->reference_number,
                $file->purchaser,
                $file->statusLabel(),
                $file->currentHolder?->full_name ?? '—',
                $file->shelf?->full_location ?? 'Not stored yet',
                optional($file->updated_at)?->format('d M Y, H:i'),
            ];
        });

        if ($format === 'pdf') {
            return $this->dashboardPdf('exports.dashboard-section', [
                'title' => 'Live custody export',
                'subtitle' => 'Scope: '.match ($scope) {
                    'registered' => 'Active registered',
                    'pending' => 'Pending review',
                    'completed' => 'Ready for storage',
                    'stored' => 'Currently stored',
                    'borrowed' => 'Currently borrowed',
                    'missing' => 'Missing',
                    default => 'All statuses',
                },
                'logo' => $this->dashboardLogoDataUri(),
                'columns' => ['#', 'Reference', 'Purchaser', 'Status', 'Current holder', 'Location', 'Last updated'],
                'rows' => $rows->all(),
            ])->setPaper('a4', 'landscape')->download('dashboard-custody-view-'.now()->format('Ymd-His').'.pdf');
        }

        return $this->streamCsvDownload(
            'dashboard-custody-view-'.now()->format('Ymd-His').'.csv',
            ['#', 'Reference', 'Purchaser', 'Status', 'Current holder', 'Location', 'Last updated'],
            $rows
        );
    }

    private function dashboardTrendExport(Request $request): StreamedResponse|BinaryFileResponse|Response
    {
        $format = $this->dashboardExportFormat($request);
        $chart = $this->dashboardRegistrationChart(
            $this->dashboardChartMode($request->string('chart')->value() ?: '30d'),
            $this->dashboardSelectedMonth($request->string('month')->value())
        );

        $rows = collect($chart['points'])->map(fn (array $point) => [$point['label'], $point['value']]);

        if ($format === 'pdf') {
            return $this->dashboardPdf('exports.dashboard-section', [
                'title' => 'Case registration trend export',
                'subtitle' => $chart['period_label'],
                'logo' => $this->dashboardLogoDataUri(),
                'columns' => ['Period', 'Registered cases'],
                'rows' => $rows->all(),
            ])->setPaper('a4')->download('dashboard-registration-trend-'.now()->format('Ymd-His').'.pdf');
        }

        return $this->streamCsvDownload(
            'dashboard-registration-trend-'.now()->format('Ymd-His').'.csv',
            ['Period', 'Registered cases'],
            $rows
        );
    }

    private function dashboardPdf(string $view, array $data)
    {
        return Pdf::loadView($view, $data)->setOption('enable_php', true);
    }

    private function dashboardRegistrationSummary(): array
    {
        $todayStart = Carbon::today()->startOfDay();
        $todayEnd = Carbon::today()->endOfDay();
        $weekStart = Carbon::today()->startOfWeek();
        $monthStart = Carbon::today()->startOfMonth();
        $yearStart = Carbon::today()->startOfYear();

        return [
            'registered_today' => LegalFile::active()->whereBetween('created_at', [$todayStart, $todayEnd])->count(),
            'registered_week' => LegalFile::active()->whereBetween('created_at', [$weekStart, $todayEnd])->count(),
            'registered_month' => LegalFile::active()->whereBetween('created_at', [$monthStart, $todayEnd])->count(),
            'registered_year' => LegalFile::active()->whereBetween('created_at', [$yearStart, $todayEnd])->count(),
        ];
    }

    private function dashboardRegistrationRecords(string $scope): Collection
    {
        $query = LegalFile::active()->orderByDesc('created_at');
        $todayStart = Carbon::today()->startOfDay();
        $todayEnd = Carbon::today()->endOfDay();

        match ($scope) {
            'today' => $query->whereBetween('created_at', [$todayStart, $todayEnd]),
            'week' => $query->whereBetween('created_at', [Carbon::today()->startOfWeek(), $todayEnd]),
            'month' => $query->whereBetween('created_at', [Carbon::today()->startOfMonth(), $todayEnd]),
            'year', 'all' => $query->whereBetween('created_at', [Carbon::today()->startOfYear(), $todayEnd]),
            default => null,
        };

        return $query->get([
            'reference_number',
            'purchaser',
            'vendor',
            'property',
            'status',
            'created_at',
        ]);
    }

    private function dashboardExportFormat(Request $request): string
    {
        return $request->string('format')->lower()->value() === 'pdf' ? 'pdf' : 'csv';
    }

    private function dashboardChartMode(string $mode): string
    {
        return in_array($mode, ['30d', 'year', 'month'], true) ? $mode : '30d';
    }

    private function dashboardSelectedMonth(?string $month): Carbon
    {
        if (! $month || preg_match('/^\d{4}-\d{2}$/', $month) !== 1) {
            return Carbon::today()->startOfMonth();
        }

        try {
            return Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable) {
            return Carbon::today()->startOfMonth();
        }
    }

    private function dashboardRegistrationChart(string $mode, Carbon $selectedMonth): array
    {
        $todayEnd = Carbon::today()->endOfDay();

        return match ($mode) {
            'year' => $this->dashboardThisYearChart($todayEnd),
            'month' => $this->dashboardSpecificMonthChart($selectedMonth),
            default => $this->dashboardThirtyDayChart($todayEnd),
        };
    }

    private function dashboardThirtyDayChart(Carbon $todayEnd): array
    {
        $start = $todayEnd->copy()->subDays(29)->startOfDay();
        $counts = $this->dashboardRegisteredCountsByDay($start, $todayEnd->copy()->endOfDay());

        return [
            'period_label' => 'Last 30 days',
            'points' => collect(range(0, 29))->map(function (int $offset) use ($start, $counts): array {
                $date = $start->copy()->addDays($offset);
                return [
                    'label' => $date->format('d M'),
                    'value' => (int) ($counts[$date->toDateString()] ?? 0),
                ];
            }),
        ];
    }

    private function dashboardThisYearChart(Carbon $todayEnd): array
    {
        $start = $todayEnd->copy()->startOfYear();
        $end = $todayEnd->copy()->endOfMonth();
        $counts = $this->dashboardRegisteredCountsByMonth($start, $end);

        return [
            'period_label' => 'This year to date',
            'points' => collect(range(0, $todayEnd->month - 1))->map(function (int $offset) use ($start, $counts): array {
                $month = $start->copy()->addMonths($offset);
                return [
                    'label' => $month->format('M Y'),
                    'value' => (int) ($counts[$month->format('Y-m')] ?? 0),
                ];
            }),
        ];
    }

    private function dashboardSpecificMonthChart(Carbon $selectedMonth): array
    {
        $start = $selectedMonth->copy()->startOfMonth();
        $end = $selectedMonth->copy()->endOfMonth();
        $counts = $this->dashboardRegisteredCountsByDay($start, $end);

        return [
            'period_label' => $selectedMonth->format('F Y'),
            'points' => collect(range(1, $selectedMonth->daysInMonth))->map(function (int $day) use ($selectedMonth, $counts): array {
                $date = $selectedMonth->copy()->day($day);
                return [
                    'label' => $date->format('d M'),
                    'value' => (int) ($counts[$date->toDateString()] ?? 0),
                ];
            }),
        ];
    }

    private function dashboardRegisteredCountsByDay(Carbon $start, Carbon $end): Collection
    {
        return LegalFile::query()
            ->whereBetween('created_at', [$start, $end])
            ->get(['created_at'])
            ->groupBy(fn (LegalFile $file) => $file->created_at->toDateString())
            ->map(fn (Collection $items) => $items->count());
    }

    private function dashboardRegisteredCountsByMonth(Carbon $start, Carbon $end): Collection
    {
        return LegalFile::query()
            ->whereBetween('created_at', [$start, $end])
            ->get(['created_at'])
            ->groupBy(fn (LegalFile $file) => $file->created_at->format('Y-m'))
            ->map(fn (Collection $items) => $items->count());
    }

    private function streamCsvDownload(string $filename, array $headings, Collection $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, $headings);
            foreach ($rows as $row) {
                fputcsv($stream, is_array($row) ? $row : $row->toArray());
            }
            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function dashboardLogoDataUri(): ?string
    {
        $path = public_path('images/tsangco-logo.png');

        if (! is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($contents);
    }
}
