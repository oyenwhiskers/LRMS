<?php

namespace Tests\Feature;

use App\Models\CaseCategory;
use App\Models\FileMovement;
use App\Models\LegalFile;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportSubRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_inserts_nothing_real_run_preserves_refs_and_second_run_aborts(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'import-admin@example.test']);
        Staff::factory()->create(['full_name' => 'Erica', 'is_active' => true]);
        $category = CaseCategory::query()->where('code', 'CON')->firstOrFail();

        $unrelated = LegalFile::create([
            'reference_number' => 'CON/2025/999',
            'case_category_id' => $category->id,
            'case_type' => LegalFile::TYPE_SNP,
            'matter_type' => $category->name,
            'purchaser' => 'Existing Party',
            'property' => 'Existing property',
            'status' => LegalFile::STATUS_REGISTERED,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $path = $this->writeFixture([
            $this->row(1, 'CON/2026/001', 'snp', null, 'Alice Buyer', 'Bob Seller', 'Lot 1', 'Erica'),
            $this->row(2, 'CON/2026/002', 'snp', 'by_cash', 'Cash Buyer', 'Cash Seller', 'Lot 2', 'Unknown Pic'),
            $this->row(22, 'CON/2026/022', 'mot', 'love_and_affection', 'Donne: Someone', null, 'Lot 22', 'Erica'),
        ]);

        $this->artisan('import:sub-registry', [
            '--file' => $path,
            '--user' => $admin->email,
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertSame(1, LegalFile::query()->count());
        $this->assertTrue(LegalFile::query()->whereKey($unrelated->id)->exists());

        $this->artisan('import:sub-registry', [
            '--file' => $path,
            '--user' => $admin->email,
        ])->assertSuccessful();

        $this->assertSame(4, LegalFile::query()->count());
        $this->assertDatabaseHas('legal_files', [
            'reference_number' => 'CON/2026/001',
            'case_type' => LegalFile::TYPE_SNP,
            'purchaser' => 'Alice Buyer',
        ]);
        $this->assertDatabaseHas('legal_files', [
            'reference_number' => 'CON/2026/002',
            'case_type' => LegalFile::TYPE_SNP,
            'case_sub_type' => LegalFile::SUB_TYPE_BY_CASH,
        ]);
        $this->assertDatabaseHas('legal_files', [
            'reference_number' => 'CON/2026/022',
            'case_type' => LegalFile::TYPE_MOT,
            'case_sub_type' => LegalFile::SUB_TYPE_LOVE_AND_AFFECTION,
        ]);

        $ericaId = Staff::query()->where('full_name', 'Erica')->value('id');
        $this->assertSame(
            $ericaId,
            LegalFile::query()->where('reference_number', 'CON/2026/001')->value('person_in_charge_id')
        );
        $this->assertNull(
            LegalFile::query()->where('reference_number', 'CON/2026/002')->value('person_in_charge_id')
        );

        $this->assertSame(3, FileMovement::query()->where('type', 'registered')->count());
        $this->assertTrue(LegalFile::query()->whereKey($unrelated->id)->exists());

        $sequenceName = $category->sequenceName();
        $next = (int) DB::table('system_sequences')->where('name', $sequenceName)->value('next_value');
        $this->assertGreaterThanOrEqual(23, $next);

        $this->artisan('import:sub-registry', [
            '--file' => $path,
            '--user' => $admin->email,
        ])->assertFailed();

        $this->assertSame(4, LegalFile::query()->count());
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function writeFixture(array $rows): string
    {
        $path = storage_path('framework/testing-sub-registry.json');
        file_put_contents($path, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function row(
        int $seq,
        string $ref,
        string $caseType,
        ?string $subType,
        string $purchaser,
        ?string $vendor,
        string $property,
        ?string $spaPic,
    ): array {
        return [
            'source_row' => 7 + $seq,
            'sequence_no' => $seq,
            'case_category_code' => 'CON',
            'reference_number' => $ref,
            'case_type' => $caseType,
            'case_sub_type' => $subType,
            'file_date' => '2026-01-02',
            'start_date' => '2026-01-02',
            'end_date' => null,
            'purchaser' => $purchaser,
            'vendor' => $vendor,
            'property' => $property,
            'spa_pic' => $spaPic,
            'loan_pic' => null,
            'agent' => null,
            'loan_reference' => null,
            'loan_bank_code' => null,
            'note' => null,
            'spa_date' => null,
            'adjudikasi_date' => null,
            'ckht_date' => null,
            'tb_date' => null,
            'closed_file_note' => null,
            'status_hint' => 'registered',
        ];
    }
}
