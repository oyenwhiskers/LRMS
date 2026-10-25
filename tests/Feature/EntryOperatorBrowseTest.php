<?php

namespace Tests\Feature;

use App\Models\CaseCategory;
use App\Models\EntryScan;
use App\Models\LegalFile;
use App\Models\Rack;
use App\Models\Shelf;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EntryOperatorBrowseTest extends TestCase
{
    use RefreshDatabase;

    public function test_entry_operator_can_browse_filter_and_review_others_registrations(): void
    {
        Storage::fake('local');

        $viewer = User::factory()->entry()->create(['name' => 'Viewer Op']);
        $opA = User::factory()->entry()->create(['name' => 'Operator A']);
        $opB = User::factory()->entry()->create(['name' => 'Operator B']);
        $category = CaseCategory::query()->where('code', 'CON')->firstOrFail();

        $rack = Rack::query()->create([
            'code' => 'R1',
            'name' => 'Rack 1',
            'is_active' => true,
        ]);
        $shelf = Shelf::query()->create([
            'rack_id' => $rack->id,
            'code' => 'S1',
            'name' => 'Shelf 1',
            'is_active' => true,
        ]);

        $fileA = LegalFile::create([
            'reference_number' => 'CON/2024/AAAA',
            'case_category_id' => $category->id,
            'case_type' => LegalFile::TYPE_SNP,
            'matter_type' => $category->name,
            'purchaser' => 'Alpha Buyer',
            'property' => 'Lot A',
            'title_number' => 'PN A',
            'status' => LegalFile::STATUS_STORED,
            'shelf_id' => $shelf->id,
            'created_by' => $opA->id,
            'updated_by' => $opA->id,
        ]);
        $fileB = LegalFile::create([
            'reference_number' => 'CON/2024/BBBB',
            'case_category_id' => $category->id,
            'case_type' => LegalFile::TYPE_LOAN,
            'matter_type' => $category->name,
            'purchaser' => 'Beta Buyer',
            'property' => 'Lot B',
            'title_number' => 'PN B',
            'status' => LegalFile::STATUS_REGISTERED,
            'created_by' => $opB->id,
            'updated_by' => $opB->id,
        ]);

        Storage::disk('local')->put('entry-scans/a.jpg', 'fake-image-a');
        Storage::disk('local')->put('entry-scans/b.jpg', 'fake-image-b');

        $scanA = EntryScan::create([
            'user_id' => $opA->id,
            'document_path' => 'entry-scans/a.jpg',
            'legal_file_id' => $fileA->id,
            'shelf_id' => $shelf->id,
            'status' => EntryScan::STATUS_STORED,
        ]);
        EntryScan::create([
            'user_id' => $opA->id,
            'document_path' => 'entry-scans/a2.jpg',
            'legal_file_id' => $fileA->id,
            'status' => EntryScan::STATUS_DRAFT,
        ]);
        $scanB = EntryScan::create([
            'user_id' => $opB->id,
            'document_path' => 'entry-scans/b.jpg',
            'legal_file_id' => $fileB->id,
            'status' => EntryScan::STATUS_SAVED,
        ]);

        $this->actingAs($viewer)
            ->get(route('entry.operators.index'))
            ->assertOk()
            ->assertSee('Semua', false)
            ->assertSee('Fail disimpan', false)
            ->assertSee('Belum di rak', false)
            ->assertSee('Di rak', false)
            ->assertSee('data-modal-open="ops-metric-files"', false)
            ->assertSee('data-modal-open="ops-metric-operators"', false)
            ->assertSee('data-modal-open="ops-metric-pending"', false)
            ->assertSee('data-modal-open="ops-metric-stored"', false)
            ->assertSee('class="entry-ops__metric-value">2</p>', false)
            ->assertSee('Operator A', false)
            ->assertSee('Operator B', false)
            ->assertSee('CON/2024/AAAA', false)
            ->assertSee('CON/2024/BBBB', false)
            ->assertSee('data-modal-open="operator-review-'.$scanA->id.'"', false)
            ->assertSee('data-modal-open="operator-review-'.$scanB->id.'"', false)
            ->assertDontSee('data-file-label-type="document"', false)
            ->assertDontSee('Jenis pratonton', false)
            ->assertSee('operator-review-page', false)
            ->assertSee('data-operator-review-tabs', false)
            ->assertSee('data-operator-review-tab-btn="maklumat"', false)
            ->assertSee('data-operator-review-tab-btn="dokumen"', false)
            ->assertSee('data-doc-zoom', false)
            ->assertSee('data-doc-pages', false)
            ->assertSee('Rak &amp; shelf', false)
            ->assertSee('entry-ops-shelf--review', false)
            ->assertSee('Alpha Buyer', false)
            ->assertSee('Semua jenis', false)
            ->assertSee('Semua rak', false)
            ->assertSee('entry-ops__table', false);

        $this->actingAs($viewer)
            ->get(route('entry.operators.index', ['user_id' => $opA->id]))
            ->assertOk()
            ->assertSee('CON/2024/AAAA', false)
            ->assertDontSee('CON/2024/BBBB', false)
            ->assertSee('class="entry-ops__metric-value">1</p>', false);

        $this->actingAs($viewer)
            ->get(route('entry.operators.index', ['case_type' => LegalFile::TYPE_LOAN]))
            ->assertOk()
            ->assertSee('CON/2024/BBBB', false)
            ->assertDontSee('CON/2024/AAAA', false);

        $this->actingAs($viewer)
            ->get(route('entry.operators.index', ['rack_id' => $rack->id]))
            ->assertOk()
            ->assertSee('CON/2024/AAAA', false)
            ->assertDontSee('CON/2024/BBBB', false);

        $this->actingAs($viewer)
            ->get(route('entry.operators.document', $scanB))
            ->assertOk();
    }

    public function test_firm_user_cannot_access_operator_browse(): void
    {
        $firm = User::factory()->create();

        $this->actingAs($firm)
            ->get(route('entry.operators.index'))
            ->assertForbidden();
    }
}
