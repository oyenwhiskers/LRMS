<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAiTokenUsageUnlocked;
use App\Models\AiTokenUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiTokenUsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_firm_user_cannot_open_entry_ai_usage(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('entry.ai-usage.unlock'))->assertForbidden();
        $this->actingAs($user)->get(route('entry.ai-usage.index'))->assertForbidden();
    }

    public function test_entry_user_needs_password_then_stays_unlocked_until_logout(): void
    {
        config(['lrms.ai_usage_password' => 'YKN@2026']);
        $entry = User::factory()->entry()->create();

        $this->actingAs($entry)
            ->get(route('entry.ai-usage.index'))
            ->assertRedirect(route('entry.ai-usage.unlock'));

        $this->actingAs($entry)
            ->from(route('entry.ai-usage.unlock'))
            ->post(route('entry.ai-usage.unlock.store'), ['password' => 'wrong'])
            ->assertSessionHasErrors('password');

        $this->actingAs($entry)
            ->post(route('entry.ai-usage.unlock.store'), ['password' => 'YKN@2026'])
            ->assertRedirect(route('entry.ai-usage.index'));

        $this->actingAs($entry)
            ->get(route('entry.ai-usage.index'))
            ->assertOk()
            ->assertSee('Token AI', false)
            ->assertSee('Anggaran kos', false)
            ->assertSee('Mengikut operator', false)
            ->assertDontSee('Panggilan terbaru', false);

        $this->assertSame($entry->id, session(EnsureAiTokenUsageUnlocked::SESSION_KEY));

        $this->actingAs($entry)->post(route('logout'))->assertRedirect(route('entry.login'));
        $this->assertGuest();
        $this->assertNull(session(EnsureAiTokenUsageUnlocked::SESSION_KEY));

        $this->actingAs($entry)
            ->get(route('entry.ai-usage.index'))
            ->assertRedirect(route('entry.ai-usage.unlock'));
    }

    public function test_gemini_usage_metadata_is_recorded_accurately(): void
    {
        Storage::fake('local');
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'reference_number' => 'CON/2019/0042',
                                'case_type' => 'snp',
                                'case_category_code' => 'CON',
                                'purchaser' => 'Token Test Sdn Bhd',
                            ]),
                        ]],
                    ],
                ]],
                'usageMetadata' => [
                    'promptTokenCount' => 1200,
                    'candidatesTokenCount' => 180,
                    'thoughtsTokenCount' => 40,
                    'cachedContentTokenCount' => 50,
                    'totalTokenCount' => 1420,
                ],
            ]),
        ]);

        config([
            'services.gemini.key' => 'test-key',
            'services.gemini.model' => 'gemini-3.1-flash-lite',
        ]);

        $entry = User::factory()->entry()->create();

        $this->actingAs($entry)
            ->post(route('entry.scan.store'), [
                'document' => UploadedFile::fake()->image('case.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $usage = AiTokenUsage::query()->sole();
        $this->assertSame($entry->id, $usage->user_id);
        $this->assertSame(AiTokenUsage::FEATURE_ENTRY_CASE_DRAFT, $usage->feature);
        $this->assertSame('gemini-3.1-flash-lite', $usage->model);
        $this->assertSame(1200, $usage->prompt_tokens);
        $this->assertSame(180, $usage->candidates_tokens);
        $this->assertSame(40, $usage->thoughts_tokens);
        $this->assertSame(50, $usage->cached_tokens);
        $this->assertSame(1420, $usage->total_tokens);
        $this->assertTrue($usage->success);
        $this->assertSame(200, $usage->http_status);
    }

    public function test_failed_parse_still_records_tokens_when_api_billed(): void
    {
        Storage::fake('local');
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [['text' => 'not-json']],
                    ],
                ]],
                'usageMetadata' => [
                    'promptTokenCount' => 100,
                    'candidatesTokenCount' => 10,
                    'totalTokenCount' => 110,
                ],
            ]),
        ]);

        config([
            'services.gemini.key' => 'test-key',
            'services.gemini.model' => 'gemini-3.1-flash-lite',
        ]);

        $entry = User::factory()->entry()->create();

        $this->actingAs($entry)
            ->post(route('entry.scan.store'), [
                'document' => UploadedFile::fake()->image('case.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('warning', 'Jawapan AI tidak sah. Sila isi borang sendiri.');

        $usage = AiTokenUsage::query()->sole();
        $this->assertSame(110, $usage->total_tokens);
        $this->assertFalse($usage->success);
        $this->assertSame('invalid_json', $usage->error);
    }

    public function test_dashboard_shows_tokens_cost_and_operator_filter(): void
    {
        config([
            'lrms.ai_usage_password' => 'YKN@2026',
            'lrms.ai_usage_usd_myr' => 4.20,
        ]);

        $opA = User::factory()->entry()->create(['name' => 'Operator A']);
        $opB = User::factory()->entry()->create(['name' => 'Operator B']);
        $viewer = User::factory()->entry()->create();

        AiTokenUsage::query()->create([
            'user_id' => $opA->id,
            'feature' => AiTokenUsage::FEATURE_ENTRY_CASE_DRAFT,
            'model' => 'gemini-3.1-flash-lite',
            'prompt_tokens' => 1_000_000,
            'candidates_tokens' => 0,
            'thoughts_tokens' => 0,
            'cached_tokens' => 0,
            'total_tokens' => 1_000_000,
            'http_status' => 200,
            'success' => true,
        ]);

        $old = AiTokenUsage::query()->create([
            'user_id' => $opB->id,
            'feature' => AiTokenUsage::FEATURE_ENTRY_CASE_DRAFT,
            'model' => 'gemini-3.1-flash-lite',
            'prompt_tokens' => 0,
            'candidates_tokens' => 1_000_000,
            'thoughts_tokens' => 0,
            'cached_tokens' => 0,
            'total_tokens' => 1_000_000,
            'http_status' => 200,
            'success' => true,
        ]);
        AiTokenUsage::query()->whereKey($old->id)->update([
            'created_at' => now()->subMonth(),
            'updated_at' => now()->subMonth(),
        ]);

        $this->actingAs($viewer)
            ->post(route('entry.ai-usage.unlock.store'), ['password' => 'YKN@2026'])
            ->assertRedirect(route('entry.ai-usage.index'));

        // 1M input @ $0.25 × 4.20 = RM 1.05
        $this->actingAs($viewer)
            ->get(route('entry.ai-usage.index', ['period' => 'today']))
            ->assertOk()
            ->assertSee('Operator A', false)
            ->assertDontSee('Operator B', false)
            ->assertSee('RM 1.05', false)
            ->assertSee('1,000,000', false);

        // 1M output @ $1.50 × 4.20 = RM 6.30
        $this->actingAs($viewer)
            ->get(route('entry.ai-usage.index', [
                'period' => 'month',
                'month' => now()->subMonth()->format('Y-m'),
            ]))
            ->assertOk()
            ->assertSee('Operator B', false)
            ->assertDontSee('Operator A', false)
            ->assertSee('RM 6.30', false);
    }
}
