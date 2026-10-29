<?php

namespace Tests\Feature;

use App\Models\CaseCategory;
use App\Models\EntryScan;
use App\Models\LegalFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EntryProductivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_firm_user_cannot_open_entry_productivity(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('entry.productivity.index'))->assertForbidden();
    }

    public function test_productivity_shows_today_averages_and_operator_speed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-29 12:00:00', config('app.timezone')));

        $fast = User::factory()->entry()->create(['name' => 'Fast Op']);
        $slow = User::factory()->entry()->create(['name' => 'Slow Op']);
        $category = CaseCategory::query()->where('code', 'CON')->firstOrFail();

        $this->seedStoredScan($fast, $category, 'CON/2026/0001', '2026-10-29 09:00:00');
        $this->seedStoredScan($fast, $category, 'CON/2026/0002', '2026-10-29 09:10:00');
        $this->seedStoredScan($fast, $category, 'CON/2026/0003', '2026-10-29 09:20:00');

        $this->seedStoredScan($slow, $category, 'CON/2026/0004', '2026-10-29 10:00:00');
        $this->seedStoredScan($slow, $category, 'CON/2026/0005', '2026-10-29 10:40:00');

        // Earlier this month — feeds avg/day & avg/month, not "today"
        $this->seedStoredScan($fast, $category, 'CON/2026/0006', '2026-10-15 11:00:00');

        $this->actingAs($fast)
            ->get(route('entry.productivity.index'))
            ->assertOk()
            ->assertSee('Produktiviti', false)
            ->assertSee('Jumlah daftar', false)
            ->assertSee('Status aliran', false)
            ->assertSee('Operator', false)
            ->assertSee('Hari ini', false)
            ->assertSee('Fast Op', false)
            ->assertSee('Slow Op', false)
            ->assertViewHas('registeredToday', 5)
            ->assertViewHas('monthCount', 6)
            ->assertViewHas('avgPerDay', 0.2)
            ->assertViewHas('operators', function ($operators) {
                $fast = collect($operators)->firstWhere('name', 'Fast Op');
                $slow = collect($operators)->firstWhere('name', 'Slow Op');

                return $fast
                    && $fast['count'] === 3
                    && $fast['avg_minutes'] === 10.0
                    && $slow
                    && $slow['count'] === 2
                    && $slow['avg_minutes'] === 40.0;
            });

        Carbon::setTestNow();
    }

    private function seedStoredScan(User $user, CaseCategory $category, string $ref, string $at): void
    {
        $file = LegalFile::create([
            'reference_number' => $ref,
            'case_category_id' => $category->id,
            'case_type' => LegalFile::TYPE_SNP,
            'matter_type' => $category->name,
            'purchaser' => 'Prod Test',
            'property' => 'Lot P',
            'title_number' => 'PN P',
            'status' => LegalFile::STATUS_REGISTERED,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $scan = EntryScan::create([
            'user_id' => $user->id,
            'document_path' => 'entry-scans/'.$ref.'.jpg',
            'legal_file_id' => $file->id,
            'status' => EntryScan::STATUS_SAVED,
        ]);

        $stamp = Carbon::parse($at, config('app.timezone'));
        $scan->forceFill([
            'created_at' => $stamp,
            'updated_at' => $stamp,
        ])->save();
    }
}
