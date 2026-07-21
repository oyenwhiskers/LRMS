<?php

namespace Tests\Feature;

use App\Models\Cabinet;
use App\Models\FileMovement;
use App\Models\LegalFile;
use App\Models\Room;
use App\Models\Shelf;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class LrmsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_identity_and_storage_hierarchy_can_be_managed(): void
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

        $this->actingAs($admin)->post(route('storage.rooms.store'), ['code' => 'R1', 'name' => 'Records Room'])->assertSessionHasNoErrors();
        $room = Room::firstOrFail();
        $this->actingAs($admin)->post(route('storage.cabinets.store'), ['room_id' => $room->id, 'code' => 'C1', 'name' => 'Cabinet One'])->assertSessionHasNoErrors();
        $cabinet = Cabinet::firstOrFail();
        $this->actingAs($admin)->post(route('storage.shelves.store'), ['cabinet_id' => $cabinet->id, 'code' => 'S1', 'name' => 'Shelf One'])->assertSessionHasNoErrors();

        $this->assertSame('Records Room / Cabinet One / Shelf One', Shelf::firstOrFail()->full_location);
        $this->actingAs($admin)->patch(route('storage.rooms.toggle', $room))
            ->assertSessionHasErrors('room');
    }

    public function test_file_registration_search_label_and_operational_status(): void
    {
        [$admin, $shelf, $staff] = $this->records();
        $response = $this->actingAs($admin)->post(route('files.store'), [
            'reference_number' => 'CON/2026/001',
            'loan_reference' => 'LN-100',
            'purchaser' => 'ABC SDN BHD',
            'vendor' => 'XYZ SDN BHD',
            'property' => 'Lot 123, Sandakan',
            'matter_type' => 'Conveyancing',
            'person_in_charge_id' => $staff->id,
            'shelf_id' => $shelf->id,
        ]);
        $file = LegalFile::firstOrFail();
        $response->assertRedirect(route('files.show', $file));
        $this->assertSame('FILE000001', $file->file_identifier);
        $this->assertTrue(Str::isUuid($file->qr_identifier));

        $this->actingAs($admin)->get(route('files.index', ['search' => 'ABC']))
            ->assertOk()->assertSee('CON/2026/001');
        $this->actingAs($admin)->get(route('files.label', $file))
            ->assertOk()->assertSee('Lot 123, Sandakan');
        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()->assertSee('Available');
    }

    public function test_multi_file_borrow_and_employee_verified_return_are_atomic(): void
    {
        [$admin, $shelf, $borrower] = $this->records();
        $returner = Staff::factory()->create(['staff_number' => 'EMP002']);
        $files = collect([
            LegalFile::create($this->fileData($shelf, 'CON/2026/001', $admin)),
            LegalFile::create($this->fileData($shelf, 'CON/2026/002', $admin)),
        ]);

        $this->actingAs($admin)->post(route('movements.borrow.store'), [
            'employee_qr' => $borrower->qr_identifier,
            'file_qrs' => $files->pluck('qr_identifier')->join("\n"),
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, LegalFile::where('status', LegalFile::STATUS_BORROWED)->count());
        $this->assertSame(2, FileMovement::where('type', 'borrow')->count());

        $this->actingAs($admin)->post(route('movements.return.store'), [
            'employee_qr' => $returner->qr_identifier,
            'file_qrs' => $files->pluck('qr_identifier')->join("\n"),
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame(2, LegalFile::where('status', LegalFile::STATUS_AVAILABLE)->whereNull('current_holder_id')->count());
        $movement = FileMovement::where('type', 'return')->firstOrFail();
        $this->assertSame($borrower->id, $movement->previous_holder_id);
        $this->assertSame($returner->id, $movement->employee_id);
    }

    public function test_missing_and_found_preserve_an_audit_trail(): void
    {
        [$admin, $shelf] = $this->records();
        $file = LegalFile::create($this->fileData($shelf, 'CON/2026/009', $admin));

        $this->actingAs($admin)->patch(route('files.missing', $file), ['notes' => 'Not found during shelf check'])->assertSessionHasNoErrors();
        $this->assertSame(LegalFile::STATUS_MISSING, $file->fresh()->status);
        $this->actingAs($admin)->patch(route('files.found', $file), ['notes' => 'Located in cabinet'])->assertSessionHasNoErrors();
        $this->assertSame(LegalFile::STATUS_AVAILABLE, $file->fresh()->status);
        $this->assertSame(['missing', 'found'], FileMovement::orderBy('id')->pluck('type')->all());
    }

    public function test_invalid_batch_and_inactive_employee_cannot_partially_borrow_files(): void
    {
        [$admin, $shelf, $employee] = $this->records();
        $file = LegalFile::create($this->fileData($shelf, 'CON/2026/020', $admin));

        $this->actingAs($admin)->post(route('movements.borrow.store'), [
            'employee_qr' => $employee->qr_identifier,
            'file_qrs' => $file->qr_identifier."\n".Str::uuid(),
        ])->assertSessionHasErrors('file_qrs');
        $this->assertSame(LegalFile::STATUS_AVAILABLE, $file->fresh()->status);
        $this->assertDatabaseCount('file_movements', 0);

        $employee->update(['is_active' => false]);
        $this->actingAs($admin)->post(route('movements.borrow.store'), [
            'employee_qr' => $employee->qr_identifier,
            'file_qrs' => $file->qr_identifier,
        ])->assertSessionHasErrors('employee_qr');
        $this->assertSame(LegalFile::STATUS_AVAILABLE, $file->fresh()->status);
    }

    public function test_excel_import_requires_preview_and_creates_audited_files(): void
    {
        [$admin] = $this->records();
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([
            ['Reference Number', 'Loan Reference', 'Purchaser', 'Vendor', 'Property', 'Matter Type', 'Room Code', 'Cabinet Code', 'Shelf Code'],
            ['CON/2026/050', '', 'Importer Sdn Bhd', '', 'Lot 50', 'Conveyancing', 'R1', 'C1', 'S1'],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'lrms').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        $response = $this->actingAs($admin)->post(route('imports.preview'), [
            'workbook' => new UploadedFile($path, 'registry.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ]);
        $response->assertOk()->assertSee('CON/2026/050');
        $token = session('import_preview.token');
        $this->actingAs($admin)->post(route('imports.confirm'), ['token' => $token])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('legal_files', ['reference_number' => 'CON/2026/050']);
        $this->assertDatabaseHas('import_runs', ['status' => 'completed', 'imported_rows' => 1]);
    }

    private function records(): array
    {
        $admin = User::factory()->admin()->create();
        $room = Room::create(['code' => 'R1', 'name' => 'Records Room']);
        $cabinet = Cabinet::create(['room_id' => $room->id, 'code' => 'C1', 'name' => 'Cabinet One']);
        $shelf = Shelf::create(['cabinet_id' => $cabinet->id, 'code' => 'S1', 'name' => 'Shelf One']);
        $staff = Staff::factory()->create(['staff_number' => 'EMP001']);

        return [$admin, $shelf, $staff];
    }

    private function fileData(Shelf $shelf, string $reference, User $admin): array
    {
        return [
            'reference_number' => $reference,
            'purchaser' => 'ABC SDN BHD',
            'property' => 'Sandakan property',
            'shelf_id' => $shelf->id,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ];
    }
}
