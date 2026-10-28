<?php

namespace Tests\Feature;

use App\Models\CaseCategory;
use App\Models\EntryPrintJob;
use App\Models\EntryScan;
use App\Models\LegalFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntryPrintStationTest extends TestCase
{
    use RefreshDatabase;

    public function test_entry_operator_can_enqueue_filter_and_mark_print_jobs(): void
    {
        $operatorA = User::factory()->entry()->create(['name' => 'Operator A']);
        $operatorB = User::factory()->entry()->create(['name' => 'Operator B']);
        $station = User::factory()->entry()->create(['name' => 'Station Desk']);
        $category = CaseCategory::query()->where('code', 'CON')->firstOrFail();

        $fileA = $this->makeFile($category, $operatorA, 'CON/2019/0101');
        $fileB = $this->makeFile($category, $operatorB, 'CON/2019/0202');

        $this->actingAs($operatorA)
            ->postJson(route('entry.print-jobs.store'), [
                'legal_file_id' => $fileA->id,
                'type' => 'front',
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Job berjaya dihantar ke stesen cetak')
            ->assertJsonPath('already_queued', false);

        $this->actingAs($operatorA)
            ->postJson(route('entry.print-jobs.store'), [
                'legal_file_id' => $fileA->id,
                'type' => 'front',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Job sudah dihantar ke stesen cetak')
            ->assertJsonPath('already_queued', true);

        $this->actingAs($operatorB)
            ->postJson(route('entry.print-jobs.store'), [
                'legal_file_id' => $fileB->id,
                'type' => 'side',
            ])
            ->assertCreated();

        $this->assertDatabaseCount('entry_print_jobs', 2);

        $jobA = EntryPrintJob::query()->where('legal_file_id', $fileA->id)->firstOrFail();
        $jobB = EntryPrintJob::query()->where('legal_file_id', $fileB->id)->firstOrFail();

        $this->actingAs($station)
            ->get(route('entry.print-station'))
            ->assertOk()
            ->assertSee('Stesen cetak', false)
            ->assertSee('Operator A', false)
            ->assertSee('Operator B', false)
            ->assertSee('CON/2019/0101', false)
            ->assertSee('CON/2019/0202', false)
            ->assertSee('Belum dicetak', false)
            ->assertSee('Pratonton label', false)
            ->assertSee('data-print-preview-delete', false)
            ->assertSee('Padam', false)
            ->assertSee('data-modal="entry-print-preview"', false)
            ->assertSee('data-modal="entry-print-station-filters"', false)
            ->assertSee('Cari no. rujukan', false)
            ->assertSee('Tapis lanjutan', false);

        $this->actingAs($station)
            ->get(route('entry.print-station', ['q' => '0101']))
            ->assertOk()
            ->assertSee('CON/2019/0101', false)
            ->assertDontSee('CON/2019/0202', false);
        $this->actingAs($station)
            ->getJson(route('entry.print-jobs.index', ['requested_by' => $operatorA->id]))
            ->assertOk()
            ->assertJsonCount(1, 'jobs')
            ->assertJsonPath('jobs.0.id', $jobA->id)
            ->assertJsonPath('jobs.0.operator', 'Operator A')
            ->assertJsonPath('jobs.0.status', EntryPrintJob::STATUS_PENDING)
            ->assertJsonPath('jobs.0.status_label', 'Belum dicetak')
            ->assertJsonPath('jobs.0.review_status', EntryScan::REVIEW_PENDING)
            ->assertJsonPath('jobs.0.review_status_label', 'Belum semak')
            ->assertJsonPath('jobs.0.is_verified', false)
            ->assertJsonMissing(['id' => $jobB->id]);

        EntryScan::query()->where('legal_file_id', $fileA->id)->update([
            'review_status' => EntryScan::REVIEW_VERIFIED,
        ]);

        $this->actingAs($station)
            ->getJson(route('entry.print-jobs.index', ['requested_by' => $operatorA->id]))
            ->assertOk()
            ->assertJsonPath('jobs.0.review_status', EntryScan::REVIEW_VERIFIED)
            ->assertJsonPath('jobs.0.review_status_label', 'Disahkan')
            ->assertJsonPath('jobs.0.is_verified', true);

        $this->actingAs($station)
            ->get(route('entry.print-station', ['requested_by' => $operatorA->id]))
            ->assertOk()
            ->assertSee('Pengesahan', false)
            ->assertSee('Disahkan', false)
            ->assertSee('Belum disahkan', false);

        $this->actingAs($station)
            ->getJson(route('entry.print-jobs.index', ['review' => 'verified']))
            ->assertOk()
            ->assertJsonCount(1, 'jobs')
            ->assertJsonPath('jobs.0.id', $jobA->id);

        $this->actingAs($station)
            ->getJson(route('entry.print-jobs.index', ['review' => 'unverified']))
            ->assertOk()
            ->assertJsonCount(1, 'jobs')
            ->assertJsonPath('jobs.0.id', $jobB->id);

        $this->actingAs($station)
            ->get(route('entry.files.label', ['file' => $fileA, 'type' => 'front']))
            ->assertOk()
            ->assertSee('file-label--front', false);

        $this->actingAs($station)
            ->postJson(route('entry.print-jobs.status'), [
                'ids' => [$jobA->id],
                'status' => EntryPrintJob::STATUS_PRINTED,
            ])
            ->assertOk()
            ->assertJsonPath('marked.0', $jobA->id)
            ->assertJsonPath('status', EntryPrintJob::STATUS_PRINTED);

        $this->assertDatabaseHas('entry_print_jobs', [
            'id' => $jobA->id,
            'status' => EntryPrintJob::STATUS_PRINTED,
            'printed_by' => $station->id,
        ]);

        $this->actingAs($station)
            ->getJson(route('entry.print-jobs.index'))
            ->assertOk()
            ->assertJsonCount(1, 'jobs')
            ->assertJsonPath('jobs.0.id', $jobB->id)
            ->assertJsonPath('pending_count', 1);

        $this->actingAs($station)
            ->getJson(route('entry.print-jobs.index', ['status' => 'printed']))
            ->assertOk()
            ->assertJsonCount(1, 'jobs')
            ->assertJsonPath('jobs.0.id', $jobA->id)
            ->assertJsonPath('jobs.0.status', EntryPrintJob::STATUS_PRINTED)
            ->assertJsonPath('jobs.0.status_label', 'Dicetak');

        $this->actingAs($station)
            ->get(route('entry.print-station', ['status' => 'printed']))
            ->assertOk()
            ->assertSee('Dicetak', false)
            ->assertSee('Cetak semula', false)
            ->assertSee('Tanda belum', false)
            ->assertSee('Tanda dicetak', false)
            ->assertSee('Muat semula', false)
            ->assertSee('CON/2019/0101', false);

        $this->actingAs($station)
            ->postJson(route('entry.print-jobs.status'), [
                'ids' => [$jobA->id, $jobB->id],
                'status' => EntryPrintJob::STATUS_PENDING,
            ])
            ->assertOk()
            ->assertJsonPath('marked.0', $jobA->id)
            ->assertJsonPath('status', EntryPrintJob::STATUS_PENDING);

        $this->assertDatabaseHas('entry_print_jobs', [
            'id' => $jobA->id,
            'status' => EntryPrintJob::STATUS_PENDING,
            'printed_by' => null,
            'printed_at' => null,
        ]);

        $this->actingAs($station)
            ->postJson(route('entry.print-jobs.status'), [
                'ids' => [$jobA->id, $jobB->id],
                'status' => EntryPrintJob::STATUS_PRINTED,
            ])
            ->assertOk()
            ->assertJsonCount(2, 'marked');

        $this->assertDatabaseHas('entry_print_jobs', [
            'id' => $jobA->id,
            'status' => EntryPrintJob::STATUS_PRINTED,
        ]);
        $this->assertDatabaseHas('entry_print_jobs', [
            'id' => $jobB->id,
            'status' => EntryPrintJob::STATUS_PRINTED,
        ]);
    }

    public function test_station_batch_print_combines_selected_jobs_of_same_type(): void
    {
        $operator = User::factory()->entry()->create();
        $station = User::factory()->entry()->create();
        $category = CaseCategory::query()->where('code', 'CON')->firstOrFail();
        $fileA = $this->makeFile($category, $operator, 'CON/2019/0505');
        $fileB = $this->makeFile($category, $operator, 'CON/2019/0606');
        $fileC = $this->makeFile($category, $operator, 'CON/2019/0707');

        foreach ([$fileA, $fileB] as $file) {
            $this->actingAs($operator)
                ->postJson(route('entry.print-jobs.store'), [
                    'legal_file_id' => $file->id,
                    'type' => 'front',
                ])
                ->assertCreated();
        }

        $this->actingAs($operator)
            ->postJson(route('entry.print-jobs.store'), [
                'legal_file_id' => $fileC->id,
                'type' => 'side',
            ])
            ->assertCreated();

        $frontIds = EntryPrintJob::query()
            ->where('type', EntryPrintJob::TYPE_FRONT)
            ->orderBy('id')
            ->pluck('id');
        $sideId = EntryPrintJob::query()
            ->where('type', EntryPrintJob::TYPE_SIDE)
            ->value('id');

        $frontHtml = $this->actingAs($station)
            ->get(route('entry.print-jobs.batch-print', [
                'ids' => $frontIds->implode(','),
                'type' => 'front',
            ]))
            ->assertOk()
            ->assertSee('size: 150mm 100mm', false)
            ->assertSee('CON/2019/0505', false)
            ->assertSee('CON/2019/0606', false)
            ->assertDontSee('CON/2019/0707', false)
            ->getContent();

        $this->assertSame(2, substr_count($frontHtml, 'class="file-label file-label--front"'));
        $this->assertSame(0, substr_count($frontHtml, 'class="file-label file-label--side"'));
        $this->assertStringContainsString('page-break-before: always', $frontHtml);

        $this->actingAs($station)
            ->get(route('entry.print-jobs.batch-print', [
                'ids' => $frontIds->implode(',').','.$sideId,
                'type' => 'side',
            ]))
            ->assertOk()
            ->assertSee('size: 80mm 40mm', false)
            ->assertSee('CON/2019/0707', false)
            ->assertDontSee('CON/2019/0505', false);

        $this->actingAs($station)
            ->get(route('entry.print-jobs.batch-print', [
                'ids' => '0,abc',
            ]))
            ->assertStatus(422);
    }

    public function test_entry_cannot_enqueue_label_for_file_they_do_not_own(): void
    {
        $owner = User::factory()->entry()->create();
        $other = User::factory()->entry()->create();
        $category = CaseCategory::query()->where('code', 'CON')->firstOrFail();
        $file = $this->makeFile($category, $owner, 'CON/2019/0303');

        $this->actingAs($other)
            ->postJson(route('entry.print-jobs.store'), [
                'legal_file_id' => $file->id,
                'type' => 'front',
            ])
            ->assertForbidden();
    }

    public function test_station_can_preview_and_cancel_pending_job(): void
    {
        $operator = User::factory()->entry()->create();
        $station = User::factory()->entry()->create();
        $category = CaseCategory::query()->where('code', 'CON')->firstOrFail();
        $file = $this->makeFile($category, $operator, 'CON/2019/0404');

        $this->actingAs($operator)
            ->postJson(route('entry.print-jobs.store'), [
                'legal_file_id' => $file->id,
                'type' => 'front',
            ])
            ->assertCreated();

        $job = EntryPrintJob::query()->where('legal_file_id', $file->id)->firstOrFail();

        $this->actingAs($station)
            ->getJson(route('entry.print-jobs.index'))
            ->assertOk()
            ->assertJsonPath('jobs.0.id', $job->id)
            ->assertJsonPath('jobs.0.delete_url', route('entry.print-jobs.destroy', $job, absolute: false))
            ->assertJsonFragment(['preview_url' => route('entry.files.label', [
                'file' => $file,
                'type' => 'front',
                'embed' => 1,
            ], absolute: false)]);

        $this->actingAs($station)
            ->get(route('entry.files.label', ['file' => $file, 'type' => 'front', 'embed' => 1]))
            ->assertOk()
            ->assertSee('file-label--front', false)
            ->assertDontSee('<div class="file-label-actions">', false);

        $this->actingAs($station)
            ->postJson(route('entry.print-jobs.status'), [
                'ids' => [$job->id],
                'status' => EntryPrintJob::STATUS_PRINTED,
            ])
            ->assertOk();

        // Station desk must still preview/reprint after the job is marked printed.
        $this->actingAs($station)
            ->get(route('entry.files.label', ['file' => $file, 'type' => 'front', 'embed' => 1]))
            ->assertOk()
            ->assertSee('file-label--front', false);

        $this->actingAs($station)
            ->deleteJson(route('entry.print-jobs.destroy', $job))
            ->assertOk()
            ->assertJsonPath('id', $job->id);

        $this->assertDatabaseHas('entry_print_jobs', [
            'id' => $job->id,
            'status' => EntryPrintJob::STATUS_CANCELLED,
        ]);

        $this->actingAs($station)
            ->getJson(route('entry.print-jobs.index'))
            ->assertOk()
            ->assertJsonCount(0, 'jobs');
    }

    private function makeFile(CaseCategory $category, User $owner, string $reference): LegalFile
    {
        $file = LegalFile::create([
            'reference_number' => $reference,
            'case_category_id' => $category->id,
            'case_type' => LegalFile::TYPE_SNP,
            'matter_type' => $category->name,
            'purchaser' => 'Print Me',
            'property' => 'Lot Print',
            'title_number' => 'PN PRINT',
            'status' => LegalFile::STATUS_REGISTERED,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        EntryScan::create([
            'user_id' => $owner->id,
            'document_path' => 'entry-scans/print-'.$file->id.'.jpg',
            'legal_file_id' => $file->id,
            'status' => EntryScan::STATUS_SAVED,
        ]);

        return $file;
    }
}
