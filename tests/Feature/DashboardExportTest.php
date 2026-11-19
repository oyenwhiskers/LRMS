<?php

namespace Tests\Feature;

use App\Http\Controllers\ImportExportController;
use App\Models\CaseCategory;
use App\Models\LegalFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class DashboardExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_summary_pdf_download_uses_attachment(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('exports.dashboard', [
            'section' => 'registration-summary',
            'scope' => 'all',
            'format' => 'pdf',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
    }

    public function test_registration_summary_pdf_preview_opens_inline(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('exports.dashboard', [
            'section' => 'registration-summary',
            'scope' => 'all',
            'format' => 'csv',
            'preview' => 1,
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('inline', (string) $response->headers->get('content-disposition'));
    }

    public function test_registration_summary_groups_cases_into_one_table_per_category(): void
    {
        $admin = User::factory()->admin()->create();
        $con = CaseCategory::query()->where('code', 'CON')->firstOrFail();
        $loa = CaseCategory::query()->where('code', 'LOA')->firstOrFail();

        LegalFile::create([
            'reference_number' => 'CON/2026/0100',
            'case_category_id' => $con->id,
            'case_type' => LegalFile::TYPE_SNP,
            'matter_type' => $con->name,
            'purchaser' => 'Con Purchaser',
            'vendor' => 'Con Vendor',
            'property' => 'Con Property',
            'status' => LegalFile::STATUS_REGISTERED,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        LegalFile::create([
            'reference_number' => 'LOA/2026/0100',
            'case_category_id' => $loa->id,
            'case_type' => LegalFile::TYPE_LOAN,
            'matter_type' => $loa->name,
            'purchaser' => 'Loa Purchaser',
            'vendor' => 'Loa Vendor',
            'property' => 'Loa Property',
            'status' => LegalFile::STATUS_REGISTERED,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $controller = app(ImportExportController::class);
        $records = (new ReflectionMethod($controller, 'dashboardRegistrationRecords'))->invoke($controller, 'all');
        $groups = (new ReflectionMethod($controller, 'dashboardRegistrationDetailGroups'))->invoke($controller, $records);
        $categoryTotals = (new ReflectionMethod($controller, 'dashboardRegistrationCategoryTotals'))->invoke($controller, 'all');

        $this->assertCount(2, $groups);
        $this->assertSame("{$con->code} — {$con->name}", $groups[0]['title']);
        $this->assertSame("{$loa->code} — {$loa->name}", $groups[1]['title']);
        $this->assertSame('CON/2026/0100', $groups[0]['rows'][0][1]);
        $this->assertSame('LOA/2026/0100', $groups[1]['rows'][0][1]);
        $this->assertSame([
            ["{$con->code} — {$con->name}", '1'],
            ["{$loa->code} — {$loa->name}", '1'],
        ], $categoryTotals);

        $html = view('exports.dashboard-section', [
            'title' => 'Registration summary export',
            'subtitle' => 'Case registration report',
            'logo' => null,
            'summaryTitle' => 'Registration metrics',
            'columns' => ['Metric', 'Value', 'Context'],
            'rows' => [['Cases registered this year', '2', '2026']],
            'categorySummaryTitle' => 'Total cases by category',
            'categorySummaryColumns' => ['Category', 'Total'],
            'categorySummaryRows' => $categoryTotals,
            'detailTitle' => 'Registered case list',
            'detailColumns' => ['#', 'Reference', 'Purchaser', 'Vendor', 'Property', 'Registered at', 'Current status'],
            'detailGroups' => $groups,
        ])->render();

        $this->assertSame(2, substr_count($html, 'class="detail-table"'));
        $this->assertStringContainsString('Total cases by category', $html);
        $this->assertStringContainsString('page-break-inside: avoid', $html);
        $this->assertStringContainsString("{$con->code} — {$con->name}", $html);
        $this->assertStringContainsString("{$loa->code} — {$loa->name}", $html);
    }

    public function test_pdf_row_budget_caps_preview_and_refuses_oversized_download(): void
    {
        $controller = app(ImportExportController::class);
        $budget = new ReflectionMethod($controller, 'dashboardPdfRowBudget');
        $limits = new ReflectionClass($controller);

        $previewLimit = $limits->getConstant('PDF_PREVIEW_ROW_LIMIT');
        $downloadLimit = $limits->getConstant('PDF_DOWNLOAD_ROW_LIMIT');

        $preview = $budget->invoke($controller, Request::create('/', 'GET', ['preview' => 1]), $previewLimit + 1);
        $this->assertSame($previewLimit, $preview['limit']);
        $this->assertTrue($preview['truncated']);
        $this->assertSame($previewLimit + 1, $preview['total']);

        $downloadOk = $budget->invoke($controller, Request::create('/', 'GET', ['format' => 'pdf']), $downloadLimit);
        $this->assertNull($downloadOk['limit']);
        $this->assertFalse($downloadOk['truncated']);

        $downloadTooLarge = $budget->invoke($controller, Request::create('/', 'GET', ['format' => 'pdf']), $downloadLimit + 1);
        $this->assertNull($downloadTooLarge);
    }

    public function test_registration_records_helper_respects_preview_limit(): void
    {
        $admin = User::factory()->admin()->create();
        $con = CaseCategory::query()->where('code', 'CON')->firstOrFail();

        foreach (range(1, 3) as $index) {
            LegalFile::create([
                'reference_number' => sprintf('CON/2026/%04d', $index),
                'case_category_id' => $con->id,
                'case_type' => LegalFile::TYPE_SNP,
                'matter_type' => $con->name,
                'purchaser' => "Purchaser {$index}",
                'vendor' => "Vendor {$index}",
                'property' => "Property {$index}",
                'status' => LegalFile::STATUS_REGISTERED,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);
        }

        $controller = app(ImportExportController::class);
        $records = (new ReflectionMethod($controller, 'dashboardRegistrationRecords'))->invoke($controller, 'all', 2);

        $this->assertCount(2, $records);
    }

    public function test_registration_export_can_filter_by_category_checklist(): void
    {
        $admin = User::factory()->admin()->create();
        $con = CaseCategory::query()->where('code', 'CON')->firstOrFail();
        $loa = CaseCategory::query()->where('code', 'LOA')->firstOrFail();

        LegalFile::create([
            'reference_number' => 'CON/2026/0200',
            'case_category_id' => $con->id,
            'case_type' => LegalFile::TYPE_SNP,
            'matter_type' => $con->name,
            'purchaser' => 'Con Purchaser',
            'property' => 'Con Property',
            'status' => LegalFile::STATUS_REGISTERED,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        LegalFile::create([
            'reference_number' => 'LOA/2026/0200',
            'case_category_id' => $loa->id,
            'case_type' => LegalFile::TYPE_LOAN,
            'matter_type' => $loa->name,
            'purchaser' => 'Loa Purchaser',
            'property' => 'Loa Property',
            'status' => LegalFile::STATUS_REGISTERED,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $controller = app(ImportExportController::class);
        $records = (new ReflectionMethod($controller, 'dashboardRegistrationRecords'))
            ->invoke($controller, 'all', null, [$con->id]);
        $totals = (new ReflectionMethod($controller, 'dashboardRegistrationCategoryTotals'))
            ->invoke($controller, 'all', [$con->id]);

        $this->assertCount(1, $records);
        $this->assertSame('CON/2026/0200', $records->first()->reference_number);
        $this->assertSame([["{$con->code} — {$con->name}", '1']], $totals);

        $response = $this->actingAs($admin)->get(route('exports.dashboard', [
            'section' => 'registration-summary',
            'scope' => 'all',
            'format' => 'pdf',
            'preview' => 1,
            'categories' => [$con->id],
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_dashboard_section_renders_truncation_notice(): void
    {
        $html = view('exports.dashboard-section', [
            'title' => 'Live custody export',
            'subtitle' => 'Scope: All statuses',
            'logo' => null,
            'columns' => ['#', 'Reference'],
            'rows' => [[1, 'CON/2026/0001']],
            'truncated' => true,
            'totalRows' => 1200,
            'shownRows' => 500,
        ])->render();

        $this->assertStringContainsString('Showing first 500 of 1200', $html);
        $this->assertStringContainsString('download CSV for the full list', $html);
    }

    public function test_oversized_custody_pdf_download_redirects_with_error(): void
    {
        $admin = User::factory()->admin()->create();
        $con = CaseCategory::query()->where('code', 'CON')->firstOrFail();
        $now = now();
        $batch = [];

        for ($index = 1; $index <= 2001; $index++) {
            $batch[] = [
                'file_identifier' => sprintf('LF%08d', $index),
                'qr_identifier' => (string) Str::uuid(),
                'reference_number' => sprintf('CON/2026/%04d', $index),
                'case_category_id' => $con->id,
                'case_type' => LegalFile::TYPE_SNP,
                'matter_type' => $con->name,
                'purchaser' => "Purchaser {$index}",
                'property' => "Property {$index}",
                'status' => LegalFile::STATUS_REGISTERED,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) === 500) {
                DB::table('legal_files')->insert($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            DB::table('legal_files')->insert($batch);
        }

        $response = $this->actingAs($admin)
            ->from(route('dashboard'))
            ->get(route('exports.dashboard', [
                'section' => 'custody-view',
                'scope' => 'all',
                'format' => 'pdf',
            ]));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHasErrors('export');
    }
}
