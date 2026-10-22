<?php

namespace Tests\Feature;

use App\Models\CaseCategory;
use App\Models\FileMovement;
use App\Models\LegalFile;
use App\Models\Rack;
use App\Models\Shelf;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class LrmsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_identity_can_be_managed(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('staff.store'), [
            'staff_number' => 'EMP001', 'full_name' => 'Aisyah Karim',
            'email' => 'aisyah@example.test',
        ])->assertRedirect(route('staff.index'));

        $staff = Staff::firstOrFail();
        $this->assertTrue(Str::isUuid($staff->qr_identifier));
        $this->assertStringNotContainsString($staff->staff_number, $staff->qr_identifier);
        $this->actingAs($admin)->get(route('staff.qr', $staff))->assertOk()->assertSee('Aisyah Karim');
    }

    public function test_storage_hierarchy_can_be_managed(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('storage.racks.store'), ['code' => 'A1', 'name' => 'Conveyancing Bay'])->assertSessionHasNoErrors();
        $rack = Rack::firstOrFail();
        $this->actingAs($admin)->post(route('storage.shelves.store', $rack), ['code' => 'S1', 'name' => 'Shelf One'])->assertSessionHasNoErrors();

        $this->assertSame('A1 / Shelf One', Shelf::firstOrFail()->full_location);
        $this->actingAs($admin)->patch(route('storage.racks.toggle', $rack))
            ->assertSessionHasErrors('rack');
    }

    public function test_file_registration_auto_generates_reference_from_category(): void
    {
        [$admin, $shelf, $staff, $category] = $this->records();
        $response = $this->actingAs($admin)->post(route('files.store'), [
            'case_type' => LegalFile::TYPE_SNP,
            'case_category_id' => $category->id,
            'purchaser' => 'ABC SDN BHD',
            'vendor' => 'XYZ SDN BHD',
            'property' => 'Lot 123, Sandakan',
            'title_number' => 'PN 12345',
            'person_in_charge_id' => $staff->id,
        ]);

        $file = LegalFile::firstOrFail();
        $response->assertRedirect(route('files.show', $file));
        $this->assertSame(LegalFile::STATUS_REGISTERED, $file->status);
        $this->assertNull($file->shelf_id);
        $this->assertSame($category->id, $file->case_category_id);
        $this->assertSame(LegalFile::TYPE_SNP, $file->case_type);
        $this->assertSame('PN 12345', $file->title_number);
        $this->assertMatchesRegularExpression('/^CON\/\d{4}\/\d{4}$/', $file->reference_number);
        $this->assertTrue(Str::isUuid($file->qr_identifier));
        $this->assertDatabaseHas('file_movements', [
            'legal_file_id' => $file->id,
            'type' => 'registered',
            'new_status' => LegalFile::STATUS_REGISTERED,
        ]);
    }

    public function test_loan_and_litigation_registration_use_type_specific_fields(): void
    {
        [$admin, $shelf, $staff, $category] = $this->records();
        $loanCategory = CaseCategory::query()->where('code', 'LOA')->firstOrFail();
        $litCategory = CaseCategory::query()->where('code', 'LIT')->firstOrFail();

        $this->actingAs($admin)->post(route('files.store'), [
            'case_type' => LegalFile::TYPE_LOAN,
            'case_category_id' => $loanCategory->id,
            'purchaser' => 'Ali Bin Abu',
            'bank' => 'Maybank',
            'property' => 'Lot 9, Sandakan',
            'title_number' => 'CL 045678901',
            'person_in_charge_id' => $staff->id,
        ])->assertSessionHasNoErrors();

        $loan = LegalFile::query()->where('purchaser', 'Ali Bin Abu')->firstOrFail();
        $this->assertSame(LegalFile::TYPE_LOAN, $loan->case_type);
        $this->assertSame('Maybank', $loan->bank);
        $this->assertNull($loan->vendor);
        $this->assertNull($loan->suite_number);

        $this->actingAs($admin)->post(route('files.store'), [
            'case_type' => LegalFile::TYPE_LITIGATION,
            'case_category_id' => $litCategory->id,
            'purchaser' => 'Siti Aminah',
            'suite_number' => 'SAB-22NCvC-123-2026',
            'ic_number' => '900101-12-3456',
            'person_in_charge_id' => $staff->id,
        ])->assertSessionHasNoErrors();

        $litigation = LegalFile::query()->where('purchaser', 'Siti Aminah')->firstOrFail();
        $this->assertSame(LegalFile::TYPE_LITIGATION, $litigation->case_type);
        $this->assertSame('SAB-22NCvC-123-2026', $litigation->suite_number);
        $this->assertSame('900101-12-3456', $litigation->ic_number);
        $this->assertSame('', $litigation->property);
        $this->assertNull($litigation->bank);
        $this->assertNull($litigation->title_number);
    }

    public function test_related_files_can_be_linked_many_to_many(): void
    {
        [$admin, $shelf, $staff, $category] = $this->records();
        $loanCategory = CaseCategory::query()->where('code', 'LOA')->firstOrFail();

        $spa = LegalFile::create($this->fileData($category, $admin));
        $loan = LegalFile::create($this->fileData($loanCategory, $admin, 'LOA/2026/0001'));

        $this->actingAs($admin)->put(route('files.update', $spa), [
            'purchaser' => $spa->purchaser,
            'vendor' => $spa->vendor,
            'property' => $spa->property,
            'title_number' => $spa->title_number ?: 'PN 1',
            'person_in_charge_id' => $staff->id,
            'related_file_ids' => [$loan->id],
        ])->assertSessionHasNoErrors();

        $spa->refresh()->load(['linkedAsPrimary', 'linkedAsRelated']);
        $this->assertTrue($spa->relatedFiles()->contains('id', $loan->id));
        $loan->refresh()->load(['linkedAsPrimary', 'linkedAsRelated']);
        $this->assertTrue($loan->relatedFiles()->contains('id', $spa->id));
    }

    public function test_authorised_user_can_hard_delete_a_file(): void
    {
        [$admin, $shelf, $staff, $category] = $this->records();
        $loanCategory = CaseCategory::query()->where('code', 'LOA')->firstOrFail();

        $spa = LegalFile::create($this->fileData($category, $admin, 'CON/2026/0200'));
        $loan = LegalFile::create($this->fileData($loanCategory, $admin, 'LOA/2026/0200'));
        $spa->syncRelatedFiles([$loan->id]);

        FileMovement::create([
            'legal_file_id' => $spa->id,
            'type' => 'registered',
            'processed_by' => $admin->id,
            'previous_status' => null,
            'new_status' => LegalFile::STATUS_REGISTERED,
            'occurred_at' => now(),
        ]);

        $this->actingAs($admin)
            ->delete(route('files.destroy', $spa))
            ->assertRedirect(route('files.index'));

        $this->assertDatabaseMissing('legal_files', ['id' => $spa->id]);
        $this->assertDatabaseMissing('file_movements', ['legal_file_id' => $spa->id]);
        $this->assertDatabaseMissing('legal_file_links', ['legal_file_id' => min($spa->id, $loan->id)]);
        $this->assertDatabaseHas('legal_files', ['id' => $loan->id]);
    }

    public function test_pic_intake_borrow_and_return_are_atomic_and_traceable(): void
    {
        [$admin, $shelf, $pic, $category] = $this->records();
        $borrower = Staff::factory()->create(['staff_number' => 'EMP002']);
        $returner = Staff::factory()->create(['staff_number' => 'EMP003']);
        $files = collect([
            LegalFile::create($this->fileData($category, $admin, 'CON/2026/0001')),
            LegalFile::create($this->fileData($category, $admin, 'CON/2026/0002')),
        ]);

        foreach ($files as $file) {
            $this->actingAs($admin)->post(route('completions.request', $file))->assertSessionHasNoErrors();
            $this->actingAs($admin)->post(route('completions.approve', $file))->assertSessionHasNoErrors();
        }

        $this->actingAs($admin)->post(route('movements.pic-intake.store'), [
            'file_qrs' => $files->pluck('qr_identifier')->join("\n"),
            'shelf_qr' => $shelf->qrPayload(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, LegalFile::where('status', LegalFile::STATUS_STORED)->count());

        $this->actingAs($admin)->post(route('movements.borrow.store'), [
            'employee_qr' => $borrower->qr_identifier,
            'file_qrs' => $files->pluck('qr_identifier')->join("\n"),
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, LegalFile::where('status', LegalFile::STATUS_BORROWED)->count());

        $this->actingAs($admin)->post(route('movements.return.store'), [
            'pic_qr' => $pic->qr_identifier,
            'file_qrs' => $files->pluck('qr_identifier')->join("\n"),
            'returner_qr' => $returner->qr_identifier,
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame(2, LegalFile::where('status', LegalFile::STATUS_COMPLETED)->whereNull('shelf_id')->whereNull('current_holder_id')->count());
        $movement = FileMovement::where('type', 'return')->firstOrFail();
        $this->assertSame($borrower->id, $movement->previous_holder_id);
        $this->assertSame($returner->id, $movement->employee_id);
        $this->assertSame($pic->id, $movement->receiver_id);
        $this->assertNull($movement->shelf_id);

        $this->actingAs($admin)->post(route('movements.pic-intake.store'), [
            'file_qrs' => $files->pluck('qr_identifier')->join("\n"),
            'shelf_qr' => $shelf->qrPayload(),
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, LegalFile::where('status', LegalFile::STATUS_STORED)->where('shelf_id', $shelf->id)->count());
    }

    public function test_completion_request_approve_and_reject_flow(): void
    {
        [$admin, $shelf, $staff, $category] = $this->records();
        $file = LegalFile::create($this->fileData($category, $admin, 'CON/2026/0300'));

        $this->actingAs($admin)
            ->post(route('completions.request', $file), ['notes' => 'Ready for review'])
            ->assertSessionHasNoErrors();
        $this->assertSame(LegalFile::STATUS_PENDING, $file->fresh()->status);

        $this->actingAs($admin)
            ->post(route('completions.reject', $file), ['notes' => 'Missing annexures'])
            ->assertSessionHasNoErrors();
        $this->assertSame(LegalFile::STATUS_REGISTERED, $file->fresh()->status);

        $this->actingAs($admin)->post(route('completions.request', $file))->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('completions.approve', $file))->assertSessionHasNoErrors();
        $this->assertSame(LegalFile::STATUS_COMPLETED, $file->fresh()->status);

        $this->actingAs($admin)->post(route('movements.pic-intake.store'), [
            'file_qrs' => $file->qr_identifier,
            'shelf_qr' => $shelf->qrPayload(),
        ])->assertSessionHasNoErrors();
        $this->assertSame(LegalFile::STATUS_STORED, $file->fresh()->status);

        $this->assertSame(
            ['completion_requested', 'completion_rejected', 'completion_requested', 'completion_approved', 'stored'],
            FileMovement::query()->where('legal_file_id', $file->id)->orderBy('id')->pluck('type')->all()
        );
    }

    public function test_pic_intake_rejects_registered_files(): void
    {
        [$admin, $shelf, $staff, $category] = $this->records();
        $file = LegalFile::create($this->fileData($category, $admin, 'CON/2026/0301'));

        $this->actingAs($admin)->post(route('movements.pic-intake.store'), [
            'file_qrs' => $file->qr_identifier,
            'shelf_qr' => $shelf->qrPayload(),
        ])->assertSessionHasErrors('file_qrs');

        $this->assertSame(LegalFile::STATUS_REGISTERED, $file->fresh()->status);
    }

    public function test_unauthorized_user_cannot_approve_completion(): void
    {
        [$admin, $shelf, $staff, $category] = $this->records();
        $file = LegalFile::create($this->fileData($category, $admin, 'CON/2026/0302'));
        $file->update(['status' => LegalFile::STATUS_PENDING]);

        $user = \App\Models\User::factory()->create([
            'status' => \App\Models\User::STATUS_APPROVED,
            'role' => \App\Models\User::ROLE_USER,
        ]);

        $this->actingAs($user)
            ->post(route('completions.approve', $file))
            ->assertForbidden();
    }

    public function test_missing_and_found_preserve_an_audit_trail(): void
    {
        [$admin, $shelf, $staff, $category] = $this->records();
        $file = LegalFile::create($this->storedFileData($shelf, $category, $admin, 'CON/2026/0009'));

        $this->actingAs($admin)->patch(route('files.missing', $file), ['notes' => 'Not found during shelf check'])->assertSessionHasNoErrors();
        $this->assertSame(LegalFile::STATUS_MISSING, $file->fresh()->status);
        $this->actingAs($admin)->patch(route('files.found', $file), ['notes' => 'Located on rack', 'shelf_id' => $shelf->id])->assertSessionHasNoErrors();
        $this->assertSame(LegalFile::STATUS_STORED, $file->fresh()->status);
        $this->assertSame(['missing', 'found'], FileMovement::orderBy('id')->pluck('type')->all());
    }

    public function test_file_detail_paginates_large_movement_histories(): void
    {
        [$admin, $shelf, $employee, $category] = $this->records();
        $file = LegalFile::create($this->storedFileData($shelf, $category, $admin, 'CON/2026/0010'));

        foreach (range(1, 35) as $offset) {
            FileMovement::create([
                'legal_file_id' => $file->id,
                'type' => 'audit',
                'employee_id' => $employee->id,
                'processed_by' => $admin->id,
                'shelf_id' => $shelf->id,
                'previous_status' => LegalFile::STATUS_STORED,
                'new_status' => LegalFile::STATUS_STORED,
                'occurred_at' => now()->subMinutes($offset),
            ]);
        }

        $this->actingAs($admin)
            ->get(route('files.show', $file))
            ->assertOk()
            ->assertViewHas('movements', fn ($movements) => $movements->count() === 30
                && $movements->total() === 35);
    }

    public function test_invalid_batch_and_inactive_employee_cannot_partially_borrow_files(): void
    {
        [$admin, $shelf, $employee, $category] = $this->records();
        $file = LegalFile::create($this->storedFileData($shelf, $category, $admin, 'CON/2026/0020'));

        $this->actingAs($admin)->post(route('movements.borrow.store'), [
            'employee_qr' => $employee->qr_identifier,
            'file_qrs' => $file->qr_identifier."\nmissing-file",
        ])->assertSessionHasErrors('file_qrs');
        $this->assertSame(LegalFile::STATUS_STORED, $file->fresh()->status);

        $employee->update(['is_active' => false]);
        $this->actingAs($admin)->post(route('movements.borrow.store'), [
            'employee_qr' => $employee->qr_identifier,
            'file_qrs' => $file->qr_identifier,
        ])->assertSessionHasErrors('employee_qr');
        $this->assertSame(LegalFile::STATUS_STORED, $file->fresh()->status);
    }

    public function test_excel_import_requires_preview_and_creates_pending_storage_files(): void
    {
        [$admin, $shelf, $staff, $category] = $this->records();
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([
            ['Case Category Code', 'Purchaser', 'Vendor', 'Property'],
            ['CON', 'Importer Sdn Bhd', '', 'Lot 50'],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'lrms').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        $response = $this->actingAs($admin)->post(route('imports.preview'), [
            'workbook' => new UploadedFile($path, 'registry.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ]);
        $response->assertOk()->assertSee('Importer Sdn Bhd');
        $token = session('import_preview.token');
        $this->actingAs($admin)->post(route('imports.confirm'), ['token' => $token])->assertSessionHasNoErrors();
        $imported = LegalFile::query()->where('purchaser', 'Importer Sdn Bhd')->firstOrFail();
        $this->assertSame(LegalFile::STATUS_REGISTERED, $imported->status);
        $this->assertSame($category->id, $imported->case_category_id);
        $this->assertMatchesRegularExpression('/^CON\/\d{4}\/\d{4}$/', $imported->reference_number);
        $this->assertDatabaseHas('import_runs', ['status' => 'completed', 'imported_rows' => 1]);
    }

    public function test_dashboard_shows_management_kpis_live_status_and_chart_modes(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 16, 10, 0, 0, config('app.timezone')));
        [$admin, $shelf, $staff, $category] = $this->records();

        $registeredToday = LegalFile::create($this->fileData($category, $admin, 'CON/2026/0100'));
        $registeredWeek = LegalFile::create($this->fileData($category, $admin, 'CON/2026/0101'));
        $registeredMonth = LegalFile::create($this->fileData($category, $admin, 'CON/2026/0102'));
        $registeredYear = LegalFile::create($this->fileData($category, $admin, 'CON/2026/0103'));
        $stored = LegalFile::create($this->storedFileData($shelf, $category, $admin, 'CON/2026/0104'));
        $borrowed = LegalFile::create(array_replace($this->storedFileData($shelf, $category, $admin, 'CON/2026/0105'), [
            'status' => LegalFile::STATUS_BORROWED,
        ]));
        $missing = LegalFile::create(array_replace($this->storedFileData($shelf, $category, $admin, 'CON/2026/0106'), [
            'status' => LegalFile::STATUS_MISSING,
        ]));

        $this->setCreatedAt($registeredToday, '2026-08-16 09:00:00');
        $this->setCreatedAt($registeredWeek, '2026-08-13 09:00:00');
        $this->setCreatedAt($registeredMonth, '2026-08-02 09:00:00');
        $this->setCreatedAt($registeredYear, '2026-02-10 09:00:00');
        $this->setCreatedAt($stored, '2026-07-21 09:00:00');
        $this->setCreatedAt($borrowed, '2026-07-22 09:00:00');
        $this->setCreatedAt($missing, '2026-07-23 09:00:00');

        FileMovement::create([
            'legal_file_id' => $stored->id,
            'type' => 'borrow',
            'processed_by' => $admin->id,
            'previous_status' => LegalFile::STATUS_STORED,
            'new_status' => LegalFile::STATUS_BORROWED,
            'occurred_at' => now()->setTime(11, 0),
        ]);

        FileMovement::create([
            'legal_file_id' => $stored->id,
            'type' => 'return',
            'processed_by' => $admin->id,
            'previous_status' => LegalFile::STATUS_BORROWED,
            'new_status' => LegalFile::STATUS_STORED,
            'occurred_at' => now()->setTime(14, 0),
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('registry', fn (array $registry) => $registry['registered_today'] === 1
                && $registry['registered_week'] === 2
                && $registry['registered_month'] === 3
                && $registry['registered_year'] === 7)
            ->assertViewHas('categoryRegistry', fn ($rows) => $rows->contains(
                fn (array $row) => $row['code'] === 'CON' && $row['today'] === 1 && $row['week'] === 2 && $row['month'] === 3
            ))
            ->assertViewHas('statusSummary', fn (array $summary) => $summary['active_registered'] === 4
                && $summary['pending_review'] === 0
                && $summary['ready_for_storage'] === 0
                && $summary['currently_stored'] === 1
                && $summary['currently_borrowed'] === 1
                && $summary['missing'] === 1)
            ->assertViewHas('movementSummary', fn (array $summary) => $summary['borrowed_today'] === 1
                && $summary['returned_today'] === 1)
            ->assertViewHas('registrationChart', fn (array $chart) => $chart['mode'] === '30d'
                && $chart['type'] === 'line'
                && $chart['points']->count() === 30);

        $this->actingAs($admin)
            ->get(route('dashboard', ['chart' => '12m']))
            ->assertOk()
            ->assertViewHas('registrationChart', fn (array $chart) => $chart['mode'] === 'year'
                && $chart['type'] === 'line'
                && $chart['points']->count() === 8);

        $this->actingAs($admin)
            ->get(route('dashboard', ['chart' => 'month', 'month' => '2026-08']))
            ->assertOk()
            ->assertViewHas('registrationChart', fn (array $chart) => $chart['mode'] === 'month'
                && $chart['type'] === 'line'
                && $chart['selected_month'] === '2026-08'
                && $chart['points']->count() === 31);

        Carbon::setTestNow();
    }

    private function records(): array
    {
        $staff = Staff::factory()->create(['staff_number' => 'EMP001']);
        $admin = User::factory()->admin()->create(['staff_id' => $staff->id]);
        $rack = Rack::create(['code' => 'A1', 'name' => 'Conveyancing Bay']);
        $shelf = Shelf::create(['rack_id' => $rack->id, 'code' => 'S1', 'name' => 'Shelf One']);
        $category = CaseCategory::query()->where('code', 'CON')->firstOrFail();

        return [$admin, $shelf, $staff, $category];
    }

    private function fileData(CaseCategory $category, User $admin, ?string $reference = null): array
    {
        return [
            'reference_number' => $reference ?? $category->formatReference(1),
            'case_category_id' => $category->id,
            'case_type' => LegalFile::TYPE_SNP,
            'matter_type' => $category->name,
            'purchaser' => 'ABC SDN BHD',
            'property' => 'Sandakan property',
            'title_number' => 'PN 1001',
            'status' => LegalFile::STATUS_REGISTERED,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ];
    }

    private function storedFileData(Shelf $shelf, CaseCategory $category, User $admin, string $reference): array
    {
        return array_replace($this->fileData($category, $admin, $reference), [
            'shelf_id' => $shelf->id,
            'status' => LegalFile::STATUS_STORED,
        ]);
    }

    private function setCreatedAt(LegalFile $file, string $timestamp): void
    {
        $moment = Carbon::parse($timestamp, config('app.timezone'));
        $file->timestamps = false;
        $file->forceFill([
            'created_at' => $moment,
            'updated_at' => $moment,
        ])->save();
    }
}
