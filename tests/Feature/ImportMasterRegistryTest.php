<?php

namespace Tests\Feature;

use App\Domain\Import\ImportMasterRegistry;
use App\Models\CaseCategory;
use App\Models\FileMovement;
use App\Models\LegalFile;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportMasterRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_preserves_refs_creates_categories_links_and_second_run_aborts(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'master-import@example.test']);
        Staff::factory()->create(['full_name' => 'Owen', 'is_active' => true]);
        Staff::factory()->create(['full_name' => 'Erica', 'is_active' => true]);

        $con = CaseCategory::query()->where('code', 'CON')->firstOrFail();
        $unrelated = LegalFile::create([
            'reference_number' => 'CON/2025/999',
            'case_category_id' => $con->id,
            'case_type' => LegalFile::TYPE_SNP,
            'matter_type' => $con->name,
            'purchaser' => 'Existing Party',
            'property' => 'Existing property',
            'status' => LegalFile::STATUS_REGISTERED,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $spa = LegalFile::create([
            'reference_number' => 'CON/2026/029',
            'case_category_id' => $con->id,
            'case_type' => LegalFile::TYPE_SNP,
            'matter_type' => $con->name,
            'purchaser' => 'SPA Party',
            'property' => 'SPA property',
            'status' => LegalFile::STATUS_REGISTERED,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $path = $this->writeFixture([
            $this->loanRow('BIMB/2026/01', 'BIMB', 'Borrower One', 'Vendor One', 'Lot 1', 'Owen', 'CON/2026/029'),
            $this->loanRow('PIB/SDK/2026/01', 'PIB', 'Borrower Two', 'Vendor Two', 'Lot 2', 'Owen', null),
            $this->projectRow('T.BOUGAINVILLA(PH1)/2026/06', 'Project Buyer', 'Developer Sdn Bhd', 'Lot 24', 'Erica', 'BIMB/2026/01'),
            $this->loanRow('B.RAKYAT/2026/08', 'B.RAKYAT', 'Borrower Three', null, 'Lot 3', 'Unknown Pic', null),
        ]);

        $this->artisan('import:master-registry', [
            '--file' => $path,
            '--user' => $admin->email,
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertSame(2, LegalFile::query()->count());
        $this->assertNull(CaseCategory::query()->where('code', 'BIMB')->first());
        $this->assertNull(CaseCategory::query()->where('code', 'BRAKYAT')->first());

        $this->artisan('import:master-registry', [
            '--file' => $path,
            '--user' => $admin->email,
        ])->assertSuccessful();

        $this->assertSame(6, LegalFile::query()->count());

        $bimb = LegalFile::query()->where('reference_number', 'BIMB/2026/01')->firstOrFail();
        $this->assertSame('BIMB/2026/01', $bimb->reference_number);
        $this->assertSame(LegalFile::TYPE_LOAN, $bimb->case_type);
        $this->assertSame('BIMB', $bimb->bank);
        $this->assertSame('BIMB', $bimb->caseCategory->code);

        $sdk = LegalFile::query()->where('reference_number', 'PIB/SDK/2026/01')->firstOrFail();
        $this->assertSame('PIB/SDK/2026/01', $sdk->reference_number);
        $this->assertSame('PIB', $sdk->caseCategory->code);

        $project = LegalFile::query()->where('reference_number', 'T.BOUGAINVILLA(PH1)/2026/06')->firstOrFail();
        $this->assertSame('T.BOUGAINVILLA(PH1)/2026/06', $project->reference_number);
        $this->assertSame(LegalFile::TYPE_SNP, $project->case_type);
        $this->assertSame('TBOUGAINVILLAPH1', $project->caseCategory->code);
        $this->assertSame('T.BOUGAINVILLA(PH1)', $project->caseCategory->name);

        $rakyat = LegalFile::query()->where('reference_number', 'B.RAKYAT/2026/08')->firstOrFail();
        $this->assertSame('B.RAKYAT/2026/08', $rakyat->reference_number);
        $this->assertSame('BRAKYAT', $rakyat->caseCategory->code);
        $this->assertNull($rakyat->person_in_charge_id);

        $owenId = Staff::query()->where('full_name', 'Owen')->value('id');
        $this->assertSame($owenId, $bimb->person_in_charge_id);

        $relatedIds = $bimb->relatedFiles()->pluck('id')->all();
        $this->assertContains($spa->id, $relatedIds);

        $projectRelated = $project->relatedFiles()->pluck('id')->all();
        $this->assertContains($bimb->id, $projectRelated);

        $this->assertSame(4, FileMovement::query()->where('type', 'registered')->whereIn('legal_file_id', [
            $bimb->id, $sdk->id, $project->id, $rakyat->id,
        ])->count());

        $this->assertTrue(LegalFile::query()->whereKey($unrelated->id)->exists());

        $bimbSeq = (int) DB::table('system_sequences')->where('name', $bimb->caseCategory->sequenceName())->value('next_value');
        $this->assertGreaterThanOrEqual(2, $bimbSeq);

        $pibSeq = (int) DB::table('system_sequences')->where('name', $sdk->caseCategory->sequenceName())->value('next_value');
        $this->assertGreaterThanOrEqual(2, $pibSeq);

        $this->artisan('import:master-registry', [
            '--file' => $path,
            '--user' => $admin->email,
        ])->assertFailed();

        $this->assertSame(6, LegalFile::query()->count());
    }

    public function test_sanitize_category_code_rules(): void
    {
        $importer = new ImportMasterRegistry;

        $this->assertSame('BIMB', $importer->sanitizeCategoryCode('BIMB'));
        $this->assertSame('BRAKYAT', $importer->sanitizeCategoryCode('B.RAKYAT'));
        $this->assertSame('TBOUGAINVILLAPH1', $importer->sanitizeCategoryCode('T.BOUGAINVILLA(PH1)'));
        $this->assertSame('TSURIAJAYAPH1', $importer->sanitizeCategoryCode('T.SURIA JAYA(PH1)'));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function writeFixture(array $rows): string
    {
        $path = storage_path('framework/testing-master-registry.json');
        file_put_contents($path, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function loanRow(
        string $ref,
        string $bank,
        string $purchaser,
        ?string $vendor,
        string $property,
        ?string $loanPic,
        ?string $spaRef,
    ): array {
        return [
            'source_sheet' => $bank,
            'source_row' => 5,
            'sequence_no' => 1,
            'registry_type' => 'loan',
            'reference_number' => $ref,
            'reference_number_raw' => $ref,
            'associated_references' => [],
            'file_date' => '2026-01-28',
            'start_date' => '2026-01-28',
            'end_date' => null,
            'purchaser' => $purchaser,
            'vendor' => $vendor,
            'property' => $property,
            'loan_reference' => null,
            'spa_reference' => $spaRef,
            'loan_bank_code' => $bank,
            'note' => null,
            'li_date' => null,
            'lo_date' => null,
            'spa_date' => null,
            'mps_date' => null,
            'validation_notes' => [],
            'loan_pic' => $loanPic,
            'spa_pic' => null,
            'agent' => null,
            'closed_file_note' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function projectRow(
        string $ref,
        string $purchaser,
        string $vendor,
        string $property,
        ?string $spaPic,
        ?string $loanRef,
    ): array {
        return [
            'source_sheet' => 'T.BOUGAINVILLA',
            'source_row' => 11,
            'sequence_no' => 6,
            'registry_type' => 'project_spa',
            'reference_number' => $ref,
            'reference_number_raw' => $ref,
            'associated_references' => [],
            'file_date' => '2026-03-10',
            'start_date' => '2026-03-10',
            'end_date' => null,
            'purchaser' => $purchaser,
            'vendor' => $vendor,
            'property' => $property,
            'loan_reference' => $loanRef,
            'spa_reference' => null,
            'loan_bank_code' => null,
            'note' => null,
            'li_date' => null,
            'lo_date' => null,
            'spa_date' => null,
            'mps_date' => null,
            'validation_notes' => [],
            'loan_pic' => null,
            'spa_pic' => $spaPic,
            'agent' => null,
            'closed_file_note' => null,
        ];
    }
}
