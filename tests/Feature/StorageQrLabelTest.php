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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorageQrLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_shelf_qr_is_unique_and_stable_when_labels_change(): void
    {
        [$admin, $rack, $shelf] = $this->location();
        $second = Shelf::create(['rack_id' => $rack->id, 'code' => 'S2', 'name' => 'Second shelf']);
        $original = $shelf->qr_identifier;

        $this->assertTrue(Str::isUuid($original));
        $this->assertNotSame($original, $second->qr_identifier);
        $this->assertSame(Shelf::QR_PREFIX.$original, $shelf->qrPayload());

        $this->actingAs($admin)->patch(route('storage.racks.update', $rack), [
            'code' => 'B1', 'name' => 'Renamed rack', 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $this->actingAs($admin)->patch(route('storage.shelves.update', $shelf), [
            'code' => 'S9', 'name' => 'Renamed shelf', 'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertSame($original, $shelf->fresh()->qr_identifier);
        $this->assertSame('B1 / Renamed shelf', $shelf->fresh()->full_location);
    }

    public function test_migration_backfills_shelves_that_already_exist(): void
    {
        [, , $shelf] = $this->location();
        $migration = require database_path('migrations/2026_10_14_000001_add_shelf_qr_identifiers.php');
        $migration->down();
        $migration->up();

        $identifier = DB::table('shelves')->where('id', $shelf->id)->value('qr_identifier');
        $this->assertTrue(Str::isUuid($identifier));
    }

    public function test_rack_and_shelf_labels_preview_and_download_with_authorization(): void
    {
        [$admin, $rack, $shelf] = $this->location();
        Shelf::create(['rack_id' => $rack->id, 'code' => 'S2', 'name' => 'Second shelf']);

        $this->actingAs($admin)->get(route('storage.racks.label', $rack))->assertOk()->assertSee('A1')->assertSee('A4 portrait');
        $this->actingAs($admin)->get(route('storage.shelves.label', $shelf))
            ->assertOk()
            ->assertSee('80mm 40mm')
            ->assertSeeInOrder([
                '<img class="shelf-label__qr" src=',
                '<div class="shelf-label__code">A1/S1</div>',
                '<div class="shelf-label__room">Main File Room</div>',
                '<div class="shelf-label__brand">TSANG &amp; CO.</div>',
            ], false)
            ->assertSee('left: 44.5mm; top: 2.5mm', false)
            ->assertSee('width: 35mm; height: 35mm', false)
            ->assertSee('transform: rotate(90deg)', false)
            ->assertDontSee('shelf-label__canvas', false)
            ->assertDontSee('<div class="name">First shelf</div>', false);
        $this->actingAs($admin)->get(route('storage.racks.shelves-labels', $rack))
            ->assertOk()->assertSee('80mm 40mm')->assertSee('A1/S2');
        foreach ([
            [route('storage.racks.label.pdf', $rack), 595.28, 841.89, 1],
            [route('storage.shelves.label.pdf', $shelf), 226.772, 113.386, 1],
            [route('storage.racks.shelves-labels.pdf', $rack), 226.772, 113.386, 2],
        ] as [$url, $width, $height, $pageCount]) {
            $response = $this->actingAs($admin)->get($url)->assertOk();
            $this->assertStringStartsWith('%PDF', $response->getContent());
            $this->assertMatchesRegularExpression(
                '/\/MediaBox\s*\[0(?:\.0+)? 0(?:\.0+)? '.preg_quote((string) $width, '/').'\d* '.preg_quote((string) $height, '/').'\d*\]/',
                $response->getContent()
            );
            $this->assertSame($pageCount, preg_match_all('/\/Type\s*\/Page\b/', $response->getContent()));
        }

        $this->actingAs(User::factory()->create())->get(route('storage.shelves.label', $shelf))->assertForbidden();
    }

    public function test_storage_print_actions_offer_direct_print_and_pdf_download(): void
    {
        [$admin, $rack, $shelf] = $this->location();

        $this->actingAs($admin)->get(route('storage.racks.show', $rack))
            ->assertOk()
            ->assertSee('data-storage-label-kind="rack"', false)
            ->assertSee('data-storage-label-print-url="'.route('storage.racks.label', $rack).'"', false)
            ->assertSee('data-storage-label-print-url="'.route('storage.racks.shelves-labels', $rack).'"', false)
            ->assertSee('data-storage-label-print-url="'.route('storage.shelves.label', $shelf).'"', false)
            ->assertSee('href="'.route('storage.shelves.label.pdf', $shelf).'"', false)
            ->assertDontSee('data-modal="storage-label-print"', false);

        $this->actingAs($admin)->get(route('storage.shelves.label', ['shelf' => $shelf, 'embed' => 1]))
            ->assertOk()
            ->assertSee('A1/S1')
            ->assertDontSee('onclick="window.print()"', false)
            ->assertDontSee('Download PDF');
    }

    public function test_print_all_shelf_stickers_places_one_on_each_80_by_40_mm_page(): void
    {
        [$admin, $rack] = $this->location();

        foreach (range(2, 9) as $number) {
            Shelf::create(['rack_id' => $rack->id, 'code' => 'S'.$number, 'name' => 'Shelf '.$number]);
        }

        $firstBatch = $this->actingAs($admin)->get(route('storage.racks.shelves-labels.pdf', $rack))->assertOk();
        $this->assertSame(9, preg_match_all('/\/Type\s*\/Page\b/', $firstBatch->getContent()));

        Shelf::create(['rack_id' => $rack->id, 'code' => 'S10', 'name' => 'Shelf 10']);

        $secondBatch = $this->actingAs($admin)->get(route('storage.racks.shelves-labels.pdf', $rack))->assertOk();
        $this->assertSame(10, preg_match_all('/\/Type\s*\/Page\b/', $secondBatch->getContent()));
    }

    public function test_store_requires_valid_active_shelf_scan_without_location_selection(): void
    {
        [$admin, $rack, $shelf] = $this->location();
        $file = $this->file($admin, 'CON/2026/9101', LegalFile::STATUS_COMPLETED);

        $this->actingAs($admin)->post(route('movements.pic-intake.store'), [
            'file_qrs' => $file->qr_identifier,
            'shelf_qr' => $file->qr_identifier,
        ])->assertSessionHasErrors('shelf_qr');

        $this->actingAs($admin)->post(route('movements.pic-intake.store'), [
            'file_qrs' => $file->qr_identifier,
        ])->assertSessionHasErrors('shelf_qr');

        $other = Shelf::create(['rack_id' => $rack->id, 'code' => 'S2', 'name' => 'Second shelf']);
        $other->update(['is_active' => false]);
        $this->actingAs($admin)->post(route('movements.pic-intake.store'), [
            'file_qrs' => $file->qr_identifier,
            'shelf_qr' => $other->qrPayload(),
        ])->assertSessionHasErrors('shelf_qr');

        $this->actingAs($admin)->post(route('movements.pic-intake.store'), [
            'file_qrs' => $file->qr_identifier,
            'shelf_qr' => $shelf->qrPayload(),
        ])->assertSessionHasNoErrors();
        $this->assertSame($shelf->id, $file->fresh()->shelf_id);

        $this->actingAs($admin)->get(route('movements.pic-intake'))
            ->assertOk()
            ->assertSee('name="shelf_qr"', false)
            ->assertSee('id="shelf_scan"', false)
            ->assertDontSee('name="shelf_id"', false);
    }

    public function test_shelf_scan_lookup_returns_current_codes_and_rejects_inactive_shelves(): void
    {
        [$admin, $rack, $shelf] = $this->location();

        $this->actingAs($admin)->get(route('scan.shelves', $shelf->qr_identifier))
            ->assertOk()->assertJson(['location' => 'A1/S1']);

        $rack->update(['code' => 'B']);
        $shelf->update(['code' => 'A1', 'name' => 'Renamed shelf']);
        $this->actingAs($admin)->get(route('scan.shelves', $shelf->qr_identifier))
            ->assertOk()->assertJson(['location' => 'B/A1']);

        $shelf->update(['is_active' => false]);
        $this->actingAs($admin)->get(route('scan.shelves', $shelf->qr_identifier))->assertNotFound();
    }

    public function test_return_from_multiple_shelves_needs_no_shelf_scan_and_waits_for_storage(): void
    {
        [$admin, $rack, $first] = $this->location();
        $second = Shelf::create(['rack_id' => $rack->id, 'code' => 'S2', 'name' => 'Second shelf']);
        $files = [
            $this->file($admin, 'CON/2026/9201', LegalFile::STATUS_BORROWED, $first),
            $this->file($admin, 'CON/2026/9202', LegalFile::STATUS_BORROWED, $second),
        ];
        $returner = Staff::factory()->create();
        $base = ['pic_qr' => $admin->staff->qr_identifier, 'returner_qr' => $returner->qr_identifier];
        $this->actingAs($admin)->post(route('movements.return.store'), $base + [
            'file_qrs' => $files[0]->qr_identifier."\n".Str::uuid(),
        ])->assertSessionHasErrors('file_qrs');
        $this->assertSame(0, FileMovement::where('type', 'return')->count());
        $this->assertSame(LegalFile::STATUS_BORROWED, $files[0]->fresh()->status);

        $this->actingAs($admin)->post(route('movements.return.store'), $base + [
            'file_qrs' => $files[0]->qr_identifier."\n".$files[1]->qr_identifier,
        ])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, FileMovement::where('type', 'return')->count());
        $this->assertNull(FileMovement::where('type', 'return')->where('legal_file_id', $files[0]->id)->value('shelf_id'));
        $this->assertSame(LegalFile::STATUS_COMPLETED, $files[0]->fresh()->status);
        $this->assertNull($files[0]->fresh()->shelf_id);
        $this->assertSame(LegalFile::STATUS_COMPLETED, $files[1]->fresh()->status);
        $this->assertNull($files[1]->fresh()->shelf_id);
    }

    private function location(): array
    {
        $staff = Staff::factory()->create();
        $admin = User::factory()->admin()->create(['staff_id' => $staff->id]);
        $rack = Rack::create(['code' => 'A1', 'name' => 'Conveyancing Bay']);
        $shelf = Shelf::create(['rack_id' => $rack->id, 'code' => 'S1', 'name' => 'First shelf']);

        return [$admin, $rack, $shelf];
    }

    private function file(User $admin, string $reference, string $status, ?Shelf $shelf = null): LegalFile
    {
        return LegalFile::create([
            'reference_number' => $reference,
            'case_category_id' => CaseCategory::where('code', 'CON')->firstOrFail()->id,
            'matter_type' => 'Conveyancing',
            'purchaser' => 'Sample purchaser',
            'property' => 'Sample property',
            'status' => $status,
            'shelf_id' => $shelf?->id,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
    }
}
