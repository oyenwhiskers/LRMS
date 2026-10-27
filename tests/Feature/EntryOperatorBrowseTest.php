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
            ->assertSee('Tanda disahkan', false)
            ->assertSee('Perlu pembetulan', false)
            ->assertSee('operators/scans/'.$scanA->id.'/review', false)
            ->assertSee('Alpha Buyer', false)
            ->assertSee('Semua jenis', false)
            ->assertSee('Semua rak', false)
            ->assertSee('Semua semakan', false)
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

    public function test_entry_peer_can_mark_verified_and_needs_correction(): void
    {
        $peer = User::factory()->entry()->create();
        $owner = User::factory()->entry()->create();
        $category = CaseCategory::query()->where('code', 'CON')->firstOrFail();

        $file = LegalFile::create([
            'reference_number' => 'CON/2024/REV1',
            'case_category_id' => $category->id,
            'case_type' => LegalFile::TYPE_SNP,
            'matter_type' => $category->name,
            'purchaser' => 'Review Buyer',
            'property' => 'Lot R',
            'title_number' => 'PN R',
            'status' => LegalFile::STATUS_STORED,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $scan = EntryScan::create([
            'user_id' => $owner->id,
            'document_path' => 'entry-scans/rev.jpg',
            'legal_file_id' => $file->id,
            'status' => EntryScan::STATUS_STORED,
            'review_status' => EntryScan::REVIEW_PENDING,
        ]);

        $this->actingAs($peer)
            ->from(route('entry.operators.index'))
            ->post(route('entry.operators.review', $scan), [
                'review_status' => EntryScan::REVIEW_NEEDS_CORRECTION,
            ])
            ->assertRedirect(route('entry.operators.index'))
            ->assertSessionHasErrors('review_notes');

        $this->actingAs($peer)
            ->from(route('entry.operators.index'))
            ->post(route('entry.operators.review', $scan), [
                'review_status' => EntryScan::REVIEW_NEEDS_CORRECTION,
                'review_notes' => 'Nama pembeli salah eja',
            ])
            ->assertRedirect(route('entry.operators.index'))
            ->assertSessionHas('success');

        $scan->refresh();
        $this->assertSame(EntryScan::REVIEW_NEEDS_CORRECTION, $scan->review_status);
        $this->assertSame($peer->id, $scan->reviewed_by);
        $this->assertNotNull($scan->reviewed_at);
        $this->assertSame('Nama pembeli salah eja', $scan->review_notes);

        $this->actingAs($peer)
            ->from(route('entry.operators.index'))
            ->post(route('entry.operators.review', $scan), [
                'review_status' => EntryScan::REVIEW_VERIFIED,
            ])
            ->assertRedirect(route('entry.operators.index'));

        $scan->refresh();
        $this->assertSame(EntryScan::REVIEW_VERIFIED, $scan->review_status);
        $this->assertSame($peer->id, $scan->reviewed_by);
        $this->assertNull($scan->review_notes);
    }

    public function test_owner_sees_needs_correction_on_home_and_history(): void
    {
        $owner = User::factory()->entry()->create();
        $other = User::factory()->entry()->create();
        $category = CaseCategory::query()->where('code', 'CON')->firstOrFail();

        $file = LegalFile::create([
            'reference_number' => 'CON/2024/FIX1',
            'case_category_id' => $category->id,
            'case_type' => LegalFile::TYPE_SNP,
            'matter_type' => $category->name,
            'purchaser' => 'Fix Buyer',
            'property' => 'Lot F',
            'title_number' => 'PN F',
            'status' => LegalFile::STATUS_STORED,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        EntryScan::create([
            'user_id' => $owner->id,
            'document_path' => 'entry-scans/fix.jpg',
            'legal_file_id' => $file->id,
            'status' => EntryScan::STATUS_STORED,
            'review_status' => EntryScan::REVIEW_NEEDS_CORRECTION,
            'reviewed_by' => $other->id,
            'reviewed_at' => now(),
            'review_notes' => 'Sila betulkan nombor geran',
        ]);

        $this->actingAs($owner)
            ->get(route('entry.home'))
            ->assertOk()
            ->assertSee('Perlu kemas kini', false)
            ->assertSee('CON/2024/FIX1', false)
            ->assertSee('Perlu pembetulan', false)
            ->assertSee('Sila betulkan nombor geran', false);

        $this->actingAs($owner)
            ->get(route('entry.history'))
            ->assertOk()
            ->assertSee('CON/2024/FIX1', false)
            ->assertSee('Perlu pembetulan', false)
            ->assertSee('Sila betulkan nombor geran', false);

        $this->actingAs($other)
            ->get(route('entry.home'))
            ->assertOk()
            ->assertDontSee('CON/2024/FIX1', false);
    }

    public function test_owner_update_resets_needs_correction_to_pending(): void
    {
        $owner = User::factory()->entry()->create();
        $peer = User::factory()->entry()->create();
        $category = CaseCategory::query()->where('code', 'CON')->firstOrFail();

        $file = LegalFile::create([
            'reference_number' => 'CON/2024/RST1',
            'case_category_id' => $category->id,
            'case_type' => LegalFile::TYPE_SNP,
            'matter_type' => $category->name,
            'purchaser' => 'Old Name',
            'property' => 'Lot Old',
            'title_number' => 'PN OLD',
            'status' => LegalFile::STATUS_STORED,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $scan = EntryScan::create([
            'user_id' => $owner->id,
            'document_path' => 'entry-scans/rst.jpg',
            'legal_file_id' => $file->id,
            'status' => EntryScan::STATUS_STORED,
            'review_status' => EntryScan::REVIEW_NEEDS_CORRECTION,
            'reviewed_by' => $peer->id,
            'reviewed_at' => now(),
            'review_notes' => 'Tukar nama pembeli',
        ]);

        $this->actingAs($owner)
            ->from(route('entry.home'))
            ->put(route('entry.scan.update', $scan), [
                '_legal_file_id' => $file->id,
                'reference_number' => 'CON/2024/RST1',
                'case_type' => LegalFile::TYPE_SNP,
                'case_category_id' => $category->id,
                'purchaser' => 'Fixed Name',
                'property' => 'Lot New',
                'title_number' => 'PN NEW',
            ])
            ->assertRedirect(route('entry.home'))
            ->assertSessionHas('success');

        $scan->refresh();
        $this->assertSame(EntryScan::REVIEW_PENDING, $scan->review_status);
        $this->assertNull($scan->reviewed_by);
        $this->assertNull($scan->reviewed_at);
        $this->assertNull($scan->review_notes);
        $this->assertSame('Fixed Name', $file->fresh()->purchaser);
    }

    public function test_draft_scan_cannot_be_reviewed_and_invalid_status_rejected(): void
    {
        $peer = User::factory()->entry()->create();
        $owner = User::factory()->entry()->create();

        $draft = EntryScan::create([
            'user_id' => $owner->id,
            'document_path' => 'entry-scans/draft.jpg',
            'status' => EntryScan::STATUS_DRAFT,
        ]);

        $this->actingAs($peer)
            ->post(route('entry.operators.review', $draft), [
                'review_status' => EntryScan::REVIEW_VERIFIED,
            ])
            ->assertNotFound();

        $category = CaseCategory::query()->where('code', 'CON')->firstOrFail();
        $file = LegalFile::create([
            'reference_number' => 'CON/2024/BAD1',
            'case_category_id' => $category->id,
            'case_type' => LegalFile::TYPE_SNP,
            'matter_type' => $category->name,
            'purchaser' => 'Bad',
            'property' => 'Lot B',
            'title_number' => 'PN B',
            'status' => LegalFile::STATUS_STORED,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        $scan = EntryScan::create([
            'user_id' => $owner->id,
            'document_path' => 'entry-scans/bad.jpg',
            'legal_file_id' => $file->id,
            'status' => EntryScan::STATUS_STORED,
        ]);

        $this->actingAs($peer)
            ->from(route('entry.operators.index'))
            ->post(route('entry.operators.review', $scan), [
                'review_status' => 'nonsense',
            ])
            ->assertRedirect(route('entry.operators.index'))
            ->assertSessionHasErrors('review_status');
    }

    public function test_review_status_filter_on_operator_browse(): void
    {
        $viewer = User::factory()->entry()->create();
        $owner = User::factory()->entry()->create();
        $category = CaseCategory::query()->where('code', 'CON')->firstOrFail();

        $pendingFile = LegalFile::create([
            'reference_number' => 'CON/2024/PEND',
            'case_category_id' => $category->id,
            'case_type' => LegalFile::TYPE_SNP,
            'matter_type' => $category->name,
            'purchaser' => 'Pending',
            'property' => 'Lot P',
            'title_number' => 'PN P',
            'status' => LegalFile::STATUS_STORED,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        $verifiedFile = LegalFile::create([
            'reference_number' => 'CON/2024/VER',
            'case_category_id' => $category->id,
            'case_type' => LegalFile::TYPE_SNP,
            'matter_type' => $category->name,
            'purchaser' => 'Verified',
            'property' => 'Lot V',
            'title_number' => 'PN V',
            'status' => LegalFile::STATUS_STORED,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        EntryScan::create([
            'user_id' => $owner->id,
            'document_path' => 'entry-scans/pend.jpg',
            'legal_file_id' => $pendingFile->id,
            'status' => EntryScan::STATUS_STORED,
            'review_status' => EntryScan::REVIEW_PENDING,
        ]);
        EntryScan::create([
            'user_id' => $owner->id,
            'document_path' => 'entry-scans/ver.jpg',
            'legal_file_id' => $verifiedFile->id,
            'status' => EntryScan::STATUS_STORED,
            'review_status' => EntryScan::REVIEW_VERIFIED,
            'reviewed_by' => $viewer->id,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->get(route('entry.operators.index', ['review_status' => EntryScan::REVIEW_VERIFIED]))
            ->assertOk()
            ->assertSee('CON/2024/VER', false)
            ->assertDontSee('CON/2024/PEND', false);
    }
}
