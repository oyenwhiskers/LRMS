<?php

namespace Tests\Feature;

use App\Models\CaseCategory;
use App\Models\EntryPrintJob;
use App\Models\EntryScan;
use App\Models\FileMovement;
use App\Models\LegalFile;
use App\Models\LegalFileParty;
use App\Models\Rack;
use App\Models\Shelf;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EntryScanRegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_entry_user_lands_on_entry_hub_and_cannot_open_firm_routes(): void
    {
        $entry = User::factory()->entry()->create();

        $this->actingAs($entry)->get('/')->assertRedirect(route('entry.home'));
        $this->actingAs($entry)->get(route('entry.home'))->assertOk();
        $this->actingAs($entry)->get(route('dashboard'))->assertRedirect(route('entry.home'));
        $this->actingAs($entry)->get(route('files.index'))->assertRedirect(route('entry.home'));
    }

    public function test_entry_has_separate_login_and_register_screens(): void
    {
        $this->get(route('entry.login'))
            ->assertOk()
            ->assertSee('Log masuk', false)
            ->assertSee('Masuk', false)
            ->assertDontSee('Sign in to TSANG', false);

        $this->get(route('entry.register'))
            ->assertOk()
            ->assertSee('Daftar akaun', false)
            ->assertDontSee('Request access', false);
    }

    public function test_entry_register_and_login_work_and_reject_firm_accounts(): void
    {
        $this->post(route('entry.register.store'), [
            'name' => 'Desk Op',
            'email' => 'desk@example.test',
            'password' => 'Password1!abcd',
            'password_confirmation' => 'Password1!abcd',
        ])->assertRedirect(route('entry.home'));

        $user = User::query()->where('email', 'desk@example.test')->firstOrFail();
        $this->assertTrue($user->isEntry());
        $this->assertTrue($user->isApproved());

        auth()->logout();

        $firm = User::factory()->admin()->create([
            'email' => 'firm@example.test',
            'password' => 'password',
        ]);

        $this->post(route('entry.login.store'), [
            'email' => $firm->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();

        $this->post(route('entry.login.store'), [
            'email' => 'desk@example.test',
            'password' => 'Password1!abcd',
        ])->assertRedirect(route('entry.home'));
    }

    public function test_non_entry_user_cannot_access_entry_hub(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('entry.home'))->assertForbidden();
    }

    public function test_admin_can_create_and_deactivate_entry_operator(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.entry-users.store'), [
            'name' => 'Entry Op',
            'email' => 'entry@example.test',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertRedirect(route('admin.entry-users.index'));

        $entry = User::query()->where('email', 'entry@example.test')->firstOrFail();
        $this->assertTrue($entry->isEntry());
        $this->assertTrue($entry->isApproved());

        $this->actingAs($admin)
            ->patch(route('admin.entry-users.deactivate', $entry))
            ->assertRedirect(route('admin.entry-users.index'));

        $this->assertSame(User::STATUS_REJECTED, $entry->fresh()->status);
    }

    public function test_scan_step_offers_camera_and_upload(): void
    {
        $entry = User::factory()->entry()->create();

        $this->actingAs($entry)
            ->get(route('entry.scan'))
            ->assertOk()
            ->assertSee('data-entry-open-camera', false)
            ->assertSee('data-entry-open-upload', false)
            ->assertSee('data-entry-upload-input', false)
            ->assertSee('data-entry-analyze', false)
            ->assertSee('data-max-pages="3"', false)
            ->assertSee('Muat naik dokumen', false);
    }

    public function test_analyze_retries_busy_model_then_succeeds(): void
    {
        Storage::fake('local');
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push(['error' => ['message' => 'busy', 'status' => 'UNAVAILABLE']], 503)
                ->push([
                    'candidates' => [[
                        'content' => [
                            'parts' => [[
                                'text' => json_encode([
                                    'reference_number' => 'CON/2019/0888',
                                    'case_type' => LegalFile::TYPE_SNP,
                                    'case_category_code' => 'CON',
                                    'purchaser' => 'Fallback Purchaser',
                                ]),
                            ]],
                        ],
                    ]],
                ]),
        ]);

        config([
            'services.gemini.key' => 'test-key',
            'services.gemini.model' => 'gemini-3.6-flash',
        ]);

        $entry = User::factory()->entry()->create();

        $this->actingAs($entry)
            ->post(route('entry.scan.store'), [
                'documents' => [UploadedFile::fake()->image('case.jpg')],
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('draft.reference_number', 'CON/2019/0888')
            ->assertJsonPath('warning', null);
    }

    public function test_analyze_accepts_multiple_pages_and_sends_all_to_gemini(): void
    {
        Storage::fake('local');
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'reference_number' => 'LOA/2020/0011',
                                'case_type' => LegalFile::TYPE_LOAN,
                                'case_category_code' => 'LOA',
                                'purchaser' => 'Page Two Borrower',
                                'property' => 'Lot 9 from page 2',
                            ]),
                        ]],
                    ],
                ]],
            ]),
        ]);

        config([
            'services.gemini.key' => 'test-key',
            'services.gemini.model' => 'gemini-3.1-flash-lite',
        ]);

        $entry = User::factory()->entry()->create();

        $response = $this->actingAs($entry)
            ->post(route('entry.scan.store'), [
                'documents' => [
                    UploadedFile::fake()->image('page1.jpg'),
                    UploadedFile::fake()->image('page2.jpg'),
                ],
            ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('draft.reference_number', 'LOA/2020/0011')
            ->assertJsonPath('draft.property', 'Lot 9 from page 2');

        $tempPath = $response->json('temp_path');
        $tempPaths = $response->json('temp_paths');
        $this->assertNotEmpty($tempPath);
        $this->assertIsArray($tempPaths);
        $this->assertCount(2, $tempPaths);
        foreach ($tempPaths as $path) {
            Storage::disk('local')->assertExists($path);
        }

        Http::assertSent(function ($request): bool {
            $parts = data_get($request->data(), 'contents.0.parts');
            if (! is_array($parts)) {
                return false;
            }
            $images = array_values(array_filter(
                $parts,
                fn ($part): bool => is_array($part) && isset($part['inlineData']['data']),
            ));
            if (count($images) !== 2) {
                return false;
            }

            return collect($images)->every(
                fn ($part): bool => data_get($part, 'mediaResolution.level') === 'media_resolution_high',
            );
        });
        $response->assertJsonPath('pages', 2);

        $category = CaseCategory::query()->where('code', 'LOA')->firstOrFail();

        $this->actingAs($entry)
            ->post(route('entry.save'), [
                'temp_paths' => $tempPaths,
                'reference_number' => 'LOA/2020/0011',
                'case_type' => LegalFile::TYPE_LOAN,
                'case_category_id' => $category->id,
                'purchaser' => 'Page Two Borrower',
                'bank' => 'Maybank',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $scan = EntryScan::firstOrFail();
        $this->assertSame([
            'entry-scans/'.$scan->id.'/page-1.jpg',
            'entry-scans/'.$scan->id.'/page-2.jpg',
        ], $scan->documentPaths());
        $this->assertSame('entry-scans/'.$scan->id.'/page-1.jpg', $scan->document_path);

        $this->actingAs($entry)
            ->get(route('entry.scan.document', ['scan' => $scan, 'page' => 2]))
            ->assertOk();
    }

    public function test_analyze_does_not_create_draft_until_confirm_save(): void
    {
        Storage::fake('local');
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'reference_number' => 'CON/2019/0042',
                                'case_type' => LegalFile::TYPE_SNP,
                                'case_category_code' => 'CON',
                                'purchaser' => 'AI Purchaser Sdn Bhd',
                                'vendor' => 'AI Vendor Sdn Bhd',
                                'property' => 'Lot 1, Sandakan',
                                'title_number' => 'PN 99999',
                                'start_date' => null,
                                'end_date' => null,
                            ]),
                        ]],
                    ],
                ]],
            ]),
        ]);

        config([
            'services.gemini.key' => 'test-key',
            'services.gemini.model' => 'gemini-2.0-flash',
        ]);

        $entry = User::factory()->entry()->create();
        $category = CaseCategory::query()->where('code', 'CON')->firstOrFail();
        $rack = Rack::create(['code' => 'A1', 'name' => 'Bay']);
        $shelf = Shelf::create(['rack_id' => $rack->id, 'code' => 'S1', 'name' => 'Shelf One']);

        $analyze = $this->actingAs($entry)
            ->post(route('entry.scan.store'), [
                'documents' => [UploadedFile::fake()->image('case.jpg')],
            ], ['Accept' => 'application/json']);

        $analyze->assertOk();
        $analyze->assertJsonPath('draft.reference_number', 'CON/2019/0042');
        $this->assertDatabaseCount('entry_scans', 0);
        $this->assertDatabaseCount('legal_files', 0);

        $tempPath = $analyze->json('temp_path');
        $this->assertNotEmpty($tempPath);
        Storage::disk('local')->assertExists($tempPath);

        $this->actingAs($entry)
            ->post(route('entry.save'), [
                'temp_path' => $tempPath,
                'reference_number' => 'CON/2019/0042',
                'case_type' => LegalFile::TYPE_SNP,
                'case_category_id' => $category->id,
                'purchaser' => 'AI Purchaser Sdn Bhd',
                'vendor' => 'AI Vendor Sdn Bhd',
                'property' => 'Lot 1, Sandakan',
                'title_number' => 'PN 99999',
            ])
            ->assertRedirect();

        $scan = EntryScan::firstOrFail();
        $file = LegalFile::firstOrFail();
        $this->assertSame('CON/2019/0042', $file->reference_number);
        $this->assertSame(LegalFile::STATUS_REGISTERED, $file->status);
        $this->assertSame(EntryScan::STATUS_SAVED, $scan->status);
        Storage::disk('local')->assertMissing($tempPath);
        Storage::disk('local')->assertExists($scan->document_path);
        $this->assertSame('entry-scans/'.$scan->id.'/page-1.jpg', $scan->document_path);

        $this->actingAs($entry)->get(route('entry.home'))
            ->assertOk()
            ->assertSee('CON/2019/0042', false)
            ->assertDontSee('Belum disimpan', false);

        $this->actingAs($entry)
            ->post(route('entry.shelf.store', $scan), [
                'shelf_qr' => $shelf->qrPayload(),
            ])
            ->assertRedirect(route('entry.home'));

        $this->assertSame(LegalFile::STATUS_STORED, $file->fresh()->status);
        $this->assertSame(EntryScan::STATUS_STORED, $scan->fresh()->status);
    }

    public function test_entry_save_allows_loan_fields_missing_from_cover(): void
    {
        Storage::fake('local');
        $entry = User::factory()->entry()->create();
        $category = CaseCategory::query()->where('code', 'LOA')->firstOrFail();
        $tempPath = 'entry-scans/tmp/'.$entry->id.'/cover.jpg';
        Storage::disk('local')->put($tempPath, 'fake');

        $this->actingAs($entry)
            ->post(route('entry.save'), [
                'temp_path' => $tempPath,
                'reference_number' => 'THY/MW/ABMB/2024/02',
                'case_type' => LegalFile::TYPE_LOAN,
                'case_category_id' => $category->id,
                'purchaser' => 'LOOK KAR YANG; LIM PUI SHEE',
                'bank' => 'ALLIANCE BANK MALAYSIA BERHAD',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $file = LegalFile::query()->where('reference_number', 'THY/MW/ABMB/2024/02')->firstOrFail();
        $this->assertSame(LegalFile::TYPE_LOAN, $file->case_type);
        $this->assertSame('ALLIANCE BANK MALAYSIA BERHAD', $file->bank);
        $this->assertSame('', (string) $file->property);
        $this->assertNull($file->title_number);
    }

    public function test_entry_save_accepts_mot_case_type_with_snp_fields(): void
    {
        Storage::fake('local');
        $entry = User::factory()->entry()->create();
        $category = CaseCategory::query()->where('code', 'CON')->firstOrFail();
        $tempPath = 'entry-scans/tmp/'.$entry->id.'/mot.jpg';
        Storage::disk('local')->put($tempPath, 'fake');

        $this->actingAs($entry)
            ->post(route('entry.save'), [
                'temp_path' => $tempPath,
                'reference_number' => 'CON/2019/0888',
                'case_type' => LegalFile::TYPE_MOT,
                'case_category_id' => $category->id,
                'purchaser' => 'Transferee Sdn Bhd',
                'vendor' => 'Transferor Sdn Bhd',
                'property' => 'Lot 88, Kota Kinabalu',
                'title_number' => 'PN 88888',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $file = LegalFile::query()->where('reference_number', 'CON/2019/0888')->firstOrFail();
        $this->assertSame(LegalFile::TYPE_MOT, $file->case_type);
        $this->assertSame('Transferee Sdn Bhd', $file->purchaser);
        $this->assertSame('Transferor Sdn Bhd', $file->vendor);
        $this->assertSame('Lot 88, Kota Kinabalu', $file->property);
        $this->assertSame('PN 88888', $file->title_number);
        $this->assertNull($file->bank);
        $this->assertNull($file->suite_number);
    }

    public function test_analyze_accepts_receipt_and_reassignment_case_type_from_gemini(): void
    {
        Storage::fake('local');
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'reference_number' => 'CON/2021/0101',
                                'case_type' => 'Receipt and Reassignment',
                                'case_category_code' => 'CON',
                                'purchaser' => 'AI Assignee',
                                'vendor' => 'AI Assignor',
                            ]),
                        ]],
                    ],
                ]],
            ]),
        ]);

        config([
            'services.gemini.key' => 'test-key',
            'services.gemini.model' => 'gemini-3.1-flash-lite',
        ]);

        $entry = User::factory()->entry()->create();

        $this->actingAs($entry)
            ->post(route('entry.scan.store'), [
                'documents' => [UploadedFile::fake()->image('receipt.jpg')],
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('draft.case_type', LegalFile::TYPE_RECEIPT_REASSIGNMENT)
            ->assertJsonPath('draft.purchaser', 'AI Assignee');

        Http::assertSent(function ($request): bool {
            $prompt = (string) data_get($request->data(), 'contents.0.parts.0.text');

            return str_contains($prompt, '"receipt_reassignment"')
                && str_contains($prompt, 'Receipt and Reassignment')
                && str_contains($prompt, 'receipt_reassignment');
        });
    }

    public function test_entry_save_accepts_receipt_and_reassignment_case_type(): void
    {
        Storage::fake('local');
        $entry = User::factory()->entry()->create();
        $category = CaseCategory::query()->where('code', 'CON')->firstOrFail();
        $tempPath = 'entry-scans/tmp/'.$entry->id.'/receipt-reassignment.jpg';
        Storage::disk('local')->put($tempPath, 'fake');

        $this->actingAs($entry)
            ->post(route('entry.save'), [
                'temp_path' => $tempPath,
                'reference_number' => 'CON/2020/0999',
                'case_type' => LegalFile::TYPE_RECEIPT_REASSIGNMENT,
                'case_category_id' => $category->id,
                'purchaser' => 'Assignee Sdn Bhd',
                'vendor' => 'Assignor Sdn Bhd',
                'property' => 'Lot 99, Kota Kinabalu',
                'title_number' => 'PN 99999',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $file = LegalFile::query()->where('reference_number', 'CON/2020/0999')->firstOrFail();
        $this->assertSame(LegalFile::TYPE_RECEIPT_REASSIGNMENT, $file->case_type);
        $this->assertSame('Receipt and Reassignment', $file->caseTypeLabel());
        $this->assertSame('Assignee Sdn Bhd', $file->purchaser);
        $this->assertSame('Assignor Sdn Bhd', $file->vendor);
        $this->assertNull($file->bank);
    }

    public function test_entry_save_stores_each_loan_borrower_with_primary(): void
    {
        Storage::fake('local');
        $entry = User::factory()->entry()->create();
        $category = CaseCategory::query()->where('code', 'LOA')->firstOrFail();
        $tempPath = 'entry-scans/tmp/'.$entry->id.'/joint.jpg';
        Storage::disk('local')->put($tempPath, 'fake');

        $this->actingAs($entry)
            ->post(route('entry.save'), [
                'temp_path' => $tempPath,
                'reference_number' => 'THY/MW/MIB/2025/01',
                'case_type' => LegalFile::TYPE_LOAN,
                'case_category_id' => $category->id,
                'bank' => 'MAYBANK ISLAMIC BERHAD',
                'borrowers' => [
                    ['name' => 'ZETTY AKMAR BINTI SAMSUL', 'ic_number' => '990930-12-7872'],
                    ['name' => 'KAMISAH BINTI KILAN', 'ic_number' => '700618-12-5030'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $file = LegalFile::query()->where('reference_number', 'THY/MW/MIB/2025/01')->firstOrFail();
        $this->assertSame('ZETTY AKMAR BINTI SAMSUL & KAMISAH BINTI KILAN', $file->purchaser);

        $parties = $file->parties()->orderBy('sort_order')->get();
        $this->assertCount(2, $parties);
        $this->assertTrue($parties[0]->is_primary);
        $this->assertSame('ZETTY AKMAR BINTI SAMSUL', $parties[0]->name);
        $this->assertSame('990930-12-7872', $parties[0]->ic_number);
        $this->assertFalse($parties[1]->is_primary);
        $this->assertSame('KAMISAH BINTI KILAN', $parties[1]->name);
        $this->assertSame('700618-12-5030', $parties[1]->ic_number);
    }

    public function test_entry_shelf_rejects_other_users_scan(): void
    {
        $owner = User::factory()->entry()->create();
        $other = User::factory()->entry()->create();
        $scan = EntryScan::create([
            'user_id' => $owner->id,
            'document_path' => 'entry-scans/x.jpg',
            'status' => EntryScan::STATUS_SAVED,
        ]);

        $this->actingAs($other)
            ->post(route('entry.shelf.store', $scan), ['shelf_qr' => 'bad'])
            ->assertForbidden();
    }

    public function test_history_can_filter_by_reference_date_and_shelf(): void
    {
        $entry = User::factory()->entry()->create();
        $category = CaseCategory::query()->where('code', 'CON')->firstOrFail();
        $rack = Rack::create(['code' => 'A1', 'name' => 'Bay']);
        $shelfA = Shelf::create(['rack_id' => $rack->id, 'code' => 'S1', 'name' => 'One']);
        $shelfB = Shelf::create(['rack_id' => $rack->id, 'code' => 'S2', 'name' => 'Two']);

        $fileA = LegalFile::create([
            'reference_number' => 'CON/2019/0101',
            'case_category_id' => $category->id,
            'case_type' => LegalFile::TYPE_SNP,
            'matter_type' => $category->name,
            'purchaser' => 'Alpha',
            'property' => 'Lot A',
            'title_number' => 'PN A',
            'status' => LegalFile::STATUS_STORED,
            'shelf_id' => $shelfA->id,
            'created_by' => $entry->id,
            'updated_by' => $entry->id,
        ]);
        $fileB = LegalFile::create([
            'reference_number' => 'CON/2020/0202',
            'case_category_id' => $category->id,
            'case_type' => LegalFile::TYPE_SNP,
            'matter_type' => $category->name,
            'purchaser' => 'Beta',
            'property' => 'Lot B',
            'title_number' => 'PN B',
            'status' => LegalFile::STATUS_STORED,
            'shelf_id' => $shelfB->id,
            'created_by' => $entry->id,
            'updated_by' => $entry->id,
        ]);

        $scanA = EntryScan::create([
            'user_id' => $entry->id,
            'document_path' => 'entry-scans/a.jpg',
            'legal_file_id' => $fileA->id,
            'shelf_id' => $shelfA->id,
            'status' => EntryScan::STATUS_STORED,
        ]);
        $scanB = EntryScan::create([
            'user_id' => $entry->id,
            'document_path' => 'entry-scans/b.jpg',
            'legal_file_id' => $fileB->id,
            'shelf_id' => $shelfB->id,
            'status' => EntryScan::STATUS_STORED,
        ]);

        $scanA->forceFill([
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ])->save();
        $scanB->forceFill([
            'created_at' => now(),
            'updated_at' => now(),
        ])->save();

        $this->actingAs($entry)
            ->get(route('entry.history', ['q' => 'CON/2019']))
            ->assertOk()
            ->assertSee('CON/2019/0101', false)
            ->assertDontSee('CON/2020/0202', false);

        $this->actingAs($entry)
            ->get(route('entry.history', ['shelf_id' => $shelfB->id]))
            ->assertOk()
            ->assertSee('CON/2020/0202', false)
            ->assertDontSee('CON/2019/0101', false);

        $this->actingAs($entry)
            ->get(route('entry.history', [
                'date_from' => now()->subDays(3)->toDateString(),
                'date_to' => now()->subDay()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('CON/2019/0101', false)
            ->assertDontSee('CON/2020/0202', false);
    }

    public function test_registered_file_opens_label_print_from_history(): void
    {
        $entry = User::factory()->entry()->create();
        $other = User::factory()->entry()->create();
        $category = CaseCategory::query()->where('code', 'CON')->firstOrFail();

        $file = LegalFile::create([
            'reference_number' => 'CON/2019/0909',
            'case_category_id' => $category->id,
            'case_type' => LegalFile::TYPE_SNP,
            'matter_type' => $category->name,
            'purchaser' => 'Print Me',
            'property' => 'Lot Print',
            'title_number' => 'PN PRINT',
            'status' => LegalFile::STATUS_REGISTERED,
            'created_by' => $entry->id,
            'updated_by' => $entry->id,
        ]);

        $scan = EntryScan::create([
            'user_id' => $entry->id,
            'document_path' => 'entry-scans/print.jpg',
            'legal_file_id' => $file->id,
            'status' => EntryScan::STATUS_STORED,
        ]);

        $this->actingAs($entry)
            ->get(route('entry.history'))
            ->assertOk()
            ->assertSee('data-modal-open="file-label-'.$file->id.'"', false)
            ->assertSee('data-file-label-modal', false)
            ->assertSee('data-file-label-type="front"', false)
            ->assertSee('data-file-label-type="side"', false)
            ->assertSee('data-file-label-type="data"', false)
            ->assertSee('data-file-label-preview="data"', false)
            ->assertSee('name="purchaser"', false)
            ->assertSee('Print Me', false)
            ->assertSee(route('entry.scan.update', $scan), false)
            ->assertSee(route('entry.files.label', $file), false)
            ->assertSee('PN PRINT', false)
            ->assertSee('class="file-label__detail"', false);

        $front = $this->actingAs($entry)
            ->get(route('entry.files.label', ['file' => $file, 'type' => 'front']))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($front, 'class="file-label file-label--front"'));

        $this->actingAs($entry)
            ->get(route('entry.files.label', ['file' => $file, 'type' => 'side']))
            ->assertOk()
            ->assertSee('file-label--side', false);

        $this->actingAs($entry)
            ->get(route('entry.files.label.pdf', ['file' => $file, 'type' => 'front']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($other)
            ->get(route('entry.files.label', $file))
            ->assertForbidden();

        $this->actingAs($entry)
            ->get(route('files.label', $file))
            ->assertRedirect(route('entry.home'));
    }

    public function test_home_fail_disimpan_count_includes_saved_scans(): void
    {
        $entry = User::factory()->entry()->create();
        $category = CaseCategory::query()->where('code', 'CON')->firstOrFail();

        $file = LegalFile::create([
            'reference_number' => 'CON/2019/0707',
            'case_category_id' => $category->id,
            'case_type' => LegalFile::TYPE_SNP,
            'matter_type' => $category->name,
            'purchaser' => 'Count Me',
            'property' => 'Lot Count',
            'title_number' => 'PN COUNT',
            'status' => LegalFile::STATUS_REGISTERED,
            'created_by' => $entry->id,
            'updated_by' => $entry->id,
        ]);

        $scan = EntryScan::create([
            'user_id' => $entry->id,
            'document_path' => 'entry-scans/count.jpg',
            'legal_file_id' => $file->id,
            'status' => EntryScan::STATUS_SAVED,
        ]);

        $this->actingAs($entry)
            ->get(route('entry.home'))
            ->assertOk()
            ->assertSee('CON/2019/0707', false)
            ->assertSee('class="entry-stat__value">1</p>', false)
            ->assertSee(route('entry.shelf', $scan), false)
            ->assertSee('Belum di rak', false)
            ->assertDontSee('data-modal-open="file-label-'.$file->id.'"', false)
            ->assertDontSee('data-file-label-type="data"', false);
    }

    public function test_saved_scan_without_shelf_goes_to_shelf_not_detail_modal(): void
    {
        $entry = User::factory()->entry()->create();
        $category = CaseCategory::query()->where('code', 'CON')->firstOrFail();

        $file = LegalFile::create([
            'reference_number' => 'CON/2019/0711',
            'case_category_id' => $category->id,
            'case_type' => LegalFile::TYPE_SNP,
            'matter_type' => $category->name,
            'purchaser' => 'Resume Shelf',
            'property' => 'Lot Resume',
            'title_number' => 'PN RESUME',
            'status' => LegalFile::STATUS_REGISTERED,
            'created_by' => $entry->id,
            'updated_by' => $entry->id,
        ]);

        $scan = EntryScan::create([
            'user_id' => $entry->id,
            'document_path' => 'entry-scans/resume.jpg',
            'legal_file_id' => $file->id,
            'status' => EntryScan::STATUS_SAVED,
        ]);

        $this->actingAs($entry)
            ->get(route('entry.home'))
            ->assertOk()
            ->assertSee(route('entry.shelf', $scan), false)
            ->assertDontSee('data-modal-open="file-label-'.$file->id.'"', false)
            ->assertDontSee('data-file-label-modal', false);

        $this->actingAs($entry)
            ->get(route('entry.history'))
            ->assertOk()
            ->assertSee(route('entry.shelf', $scan), false)
            ->assertDontSee('data-modal-open="file-label-'.$file->id.'"', false);

        $this->actingAs($entry)
            ->get(route('entry.shelf', $scan))
            ->assertOk()
            ->assertSee('Imbas QR rak', false)
            ->assertSee('Atau pilih rak secara manual', false)
            ->assertSee('CON/2019/0711', false)
            ->assertSee(route('entry.scan.destroy', $scan), false);
    }

    public function test_entry_shelf_can_store_via_manual_shelf_id(): void
    {
        $entry = User::factory()->entry()->create();
        $category = CaseCategory::query()->where('code', 'CON')->firstOrFail();
        $rack = Rack::create(['code' => 'B2', 'name' => 'Bay B']);
        $shelf = Shelf::create(['rack_id' => $rack->id, 'code' => 'S3', 'name' => 'Shelf Three']);

        $file = LegalFile::create([
            'reference_number' => 'CON/2019/0912',
            'case_category_id' => $category->id,
            'case_type' => LegalFile::TYPE_SNP,
            'matter_type' => $category->name,
            'purchaser' => 'Manual Buyer',
            'property' => 'Lot M',
            'title_number' => 'PN M',
            'status' => LegalFile::STATUS_REGISTERED,
            'created_by' => $entry->id,
            'updated_by' => $entry->id,
        ]);

        $scan = EntryScan::create([
            'user_id' => $entry->id,
            'document_path' => 'entry-scans/manual.jpg',
            'legal_file_id' => $file->id,
            'status' => EntryScan::STATUS_SAVED,
        ]);

        $this->actingAs($entry)
            ->post(route('entry.shelf.store', $scan), [
                'shelf_id' => $shelf->id,
            ])
            ->assertRedirect(route('entry.home'));

        $this->assertSame(LegalFile::STATUS_STORED, $file->fresh()->status);
        $this->assertSame($shelf->id, $file->fresh()->shelf_id);
        $this->assertSame(EntryScan::STATUS_STORED, $scan->fresh()->status);
        $this->assertSame($shelf->id, $scan->fresh()->shelf_id);
    }

    public function test_entry_user_can_update_own_scan_case_data(): void
    {
        $entry = User::factory()->entry()->create();
        $other = User::factory()->entry()->create();
        $category = CaseCategory::query()->where('code', 'CON')->firstOrFail();

        $file = LegalFile::create([
            'reference_number' => 'CON/2019/0808',
            'case_category_id' => $category->id,
            'case_type' => LegalFile::TYPE_SNP,
            'matter_type' => $category->name,
            'purchaser' => 'Old Buyer',
            'vendor' => 'Old Seller',
            'property' => 'Lot Old',
            'title_number' => 'PN OLD',
            'status' => LegalFile::STATUS_STORED,
            'created_by' => $entry->id,
            'updated_by' => $entry->id,
        ]);

        $scan = EntryScan::create([
            'user_id' => $entry->id,
            'document_path' => 'entry-scans/edit.jpg',
            'legal_file_id' => $file->id,
            'status' => EntryScan::STATUS_STORED,
        ]);

        $payload = [
            '_legal_file_id' => $file->id,
            'reference_number' => 'CON/2019/0808',
            'case_type' => LegalFile::TYPE_SNP,
            'case_category_id' => $category->id,
            'purchaser' => 'New Buyer',
            'vendor' => 'New Seller',
            'property' => 'Lot New',
            'title_number' => 'PN NEW',
        ];

        $this->actingAs($entry)
            ->from(route('entry.home'))
            ->put(route('entry.scan.update', $scan), $payload)
            ->assertRedirect(route('entry.home'))
            ->assertSessionHas('success')
            ->assertSessionHas('open_file_label', $file->id);

        $file->refresh();
        $this->assertSame('New Buyer', $file->purchaser);
        $this->assertSame('New Seller', $file->vendor);
        $this->assertSame('Lot New', $file->property);
        $this->assertSame('PN NEW', $file->title_number);

        $this->actingAs($other)
            ->put(route('entry.scan.update', $scan), $payload)
            ->assertForbidden();

        LegalFile::create([
            'reference_number' => 'CON/2019/0809',
            'case_category_id' => $category->id,
            'case_type' => LegalFile::TYPE_SNP,
            'matter_type' => $category->name,
            'purchaser' => 'Other File',
            'property' => 'Lot Other',
            'title_number' => 'PN OTHER',
            'status' => LegalFile::STATUS_REGISTERED,
            'created_by' => $entry->id,
            'updated_by' => $entry->id,
        ]);

        $this->actingAs($entry)
            ->from(route('entry.home'))
            ->put(route('entry.scan.update', $scan), [
                ...$payload,
                'reference_number' => 'CON/2019/0809',
            ])
            ->assertRedirect(route('entry.home'))
            ->assertSessionHasErrors('reference_number');

        $this->assertSame('CON/2019/0808', $file->fresh()->reference_number);
    }

    public function test_entry_user_can_view_own_scan_document_and_others_cannot(): void
    {
        Storage::fake('local');
        $owner = User::factory()->entry()->create();
        $other = User::factory()->entry()->create();
        $category = CaseCategory::query()->where('code', 'CON')->firstOrFail();
        $documentPath = 'entry-scans/1/original.jpg';
        Storage::disk('local')->put($documentPath, 'fake-image-bytes');

        $file = LegalFile::create([
            'reference_number' => 'CON/2019/0910',
            'case_category_id' => $category->id,
            'case_type' => LegalFile::TYPE_SNP,
            'matter_type' => $category->name,
            'purchaser' => 'Doc Viewer',
            'property' => 'Lot Doc',
            'title_number' => 'PN DOC',
            'status' => LegalFile::STATUS_REGISTERED,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $scan = EntryScan::create([
            'user_id' => $owner->id,
            'document_path' => $documentPath,
            'legal_file_id' => $file->id,
            'status' => EntryScan::STATUS_STORED,
        ]);

        $this->actingAs($owner)
            ->get(route('entry.history'))
            ->assertOk()
            ->assertSee('data-file-label-type="document"', false)
            ->assertSee(route('entry.scan.document', $scan), false)
            ->assertSee('Dokumen', false);

        $response = $this->actingAs($owner)
            ->get(route('entry.scan.document', $scan))
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg');

        $this->assertSame('fake-image-bytes', $response->streamedContent());

        $this->actingAs($other)
            ->get(route('entry.scan.document', $scan))
            ->assertForbidden();

        $scan->update(['document_path' => 'entry-scans/missing.jpg']);

        $this->actingAs($owner)
            ->get(route('entry.scan.document', $scan))
            ->assertNotFound();

        $scan->update(['document_path' => '']);

        $this->actingAs($owner)
            ->get(route('entry.scan.document', $scan))
            ->assertNotFound();
    }

    public function test_entry_user_can_delete_own_saved_scan_from_home_modal(): void
    {
        Storage::fake('local');
        $entry = User::factory()->entry()->create();
        $other = User::factory()->entry()->create();
        $category = CaseCategory::query()->where('code', 'CON')->firstOrFail();
        $documentPath = 'entry-scans/delete-me.jpg';
        Storage::disk('local')->put($documentPath, 'fake');

        $file = LegalFile::create([
            'reference_number' => 'CON/2019/0808',
            'case_category_id' => $category->id,
            'case_type' => LegalFile::TYPE_SNP,
            'matter_type' => $category->name,
            'purchaser' => 'Delete Me',
            'property' => 'Lot Delete',
            'title_number' => 'PN DELETE',
            'status' => LegalFile::STATUS_REGISTERED,
            'created_by' => $entry->id,
            'updated_by' => $entry->id,
        ]);

        $scan = EntryScan::create([
            'user_id' => $entry->id,
            'document_path' => $documentPath,
            'legal_file_id' => $file->id,
            'status' => EntryScan::STATUS_SAVED,
        ]);

        $this->actingAs($entry)
            ->get(route('entry.home'))
            ->assertOk()
            ->assertSee(route('entry.shelf', $scan), false)
            ->assertDontSee('data-modal-open="delete-entry-scan-'.$scan->id.'"', false);

        $this->actingAs($entry)
            ->get(route('entry.shelf', $scan))
            ->assertOk()
            ->assertSee(route('entry.scan.destroy', $scan), false);

        $this->actingAs($other)
            ->post(route('entry.scan.destroy', $scan))
            ->assertForbidden();

        $this->actingAs($entry)
            ->post(route('entry.scan.destroy', $scan))
            ->assertRedirect(route('entry.home'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('entry_scans', ['id' => $scan->id]);
        $this->assertDatabaseMissing('legal_files', ['id' => $file->id]);
        Storage::disk('local')->assertMissing($documentPath);

        $this->actingAs($entry)
            ->get(route('entry.home'))
            ->assertOk()
            ->assertSee('class="entry-stat__value">0</p>', false)
            ->assertSee('Belum ada fail', false);
    }

    public function test_entry_user_can_delete_stored_scan_with_related_rows(): void
    {
        Storage::fake('local');
        $entry = User::factory()->entry()->create();
        $category = CaseCategory::query()->where('code', 'CON')->firstOrFail();
        $rack = Rack::create(['code' => 'DEL', 'name' => 'Delete Bay']);
        $shelf = Shelf::create(['rack_id' => $rack->id, 'code' => 'S1', 'name' => 'Shelf']);
        $path = 'entry-scans/99/page-1.jpg';
        Storage::disk('local')->put($path, 'x');

        $file = LegalFile::create([
            'reference_number' => 'CON/2019/9999',
            'case_category_id' => $category->id,
            'case_type' => LegalFile::TYPE_SNP,
            'matter_type' => $category->name,
            'purchaser' => 'X',
            'property' => 'Lot X',
            'status' => LegalFile::STATUS_STORED,
            'shelf_id' => $shelf->id,
            'created_by' => $entry->id,
            'updated_by' => $entry->id,
        ]);
        LegalFileParty::create([
            'legal_file_id' => $file->id,
            'name' => 'A',
            'sort_order' => 0,
            'is_primary' => true,
        ]);
        FileMovement::create([
            'legal_file_id' => $file->id,
            'type' => 'stored',
            'processed_by' => $entry->id,
            'shelf_id' => $shelf->id,
            'previous_status' => LegalFile::STATUS_REGISTERED,
            'new_status' => LegalFile::STATUS_STORED,
            'occurred_at' => now(),
        ]);
        EntryPrintJob::create([
            'legal_file_id' => $file->id,
            'requested_by' => $entry->id,
            'type' => EntryPrintJob::TYPE_FRONT,
            'status' => EntryPrintJob::STATUS_PENDING,
        ]);
        $scan = EntryScan::create([
            'user_id' => $entry->id,
            'document_path' => $path,
            'legal_file_id' => $file->id,
            'shelf_id' => $shelf->id,
            'status' => EntryScan::STATUS_STORED,
        ]);

        $this->actingAs($entry)
            ->post(route('entry.scan.destroy', $scan))
            ->assertRedirect(route('entry.home'));

        $this->assertDatabaseMissing('entry_scans', ['id' => $scan->id]);
        $this->assertDatabaseMissing('legal_files', ['id' => $file->id]);
    }

    public function test_pic_intake_still_requires_completed_status(): void
    {
        $staff = \App\Models\Staff::factory()->create();
        $admin = User::factory()->admin()->create(['staff_id' => $staff->id]);
        $category = CaseCategory::query()->where('code', 'CON')->firstOrFail();
        $rack = Rack::create(['code' => 'A1', 'name' => 'Bay']);
        $shelf = Shelf::create(['rack_id' => $rack->id, 'code' => 'S1', 'name' => 'Shelf One']);
        $file = LegalFile::create([
            'reference_number' => $category->formatReference(1),
            'case_category_id' => $category->id,
            'case_type' => LegalFile::TYPE_SNP,
            'matter_type' => $category->name,
            'purchaser' => 'ABC',
            'property' => 'Lot',
            'title_number' => 'PN 1',
            'status' => LegalFile::STATUS_REGISTERED,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post(route('movements.pic-intake.store'), [
            'file_qrs' => $file->qr_identifier,
            'shelf_qr' => $shelf->qrPayload(),
        ])->assertSessionHasErrors('file_qrs');
    }
}
