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

    public function test_today_shows_operator_minute_and_hour_rates(): void
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
            ->assertSee('Fail/min', false)
            ->assertSee('Fail/jam', false)
            ->assertViewHas('registeredToday', 5)
            ->assertViewHas('monthCount', 6)
            ->assertViewHas('avgPerDay', 0.2)
            ->assertViewHas('operators', function ($operators) {
                $fast = collect($operators)->firstWhere('name', 'Fast Op');
                $slow = collect($operators)->firstWhere('name', 'Slow Op');

                return $fast
                    && $fast['count'] === 3
                    && $fast['files_per_minute'] === 0.15
                    && $fast['files_per_hour'] === 9.0
                    && ! array_key_exists('files_per_day', $fast)
                    && $slow
                    && $slow['count'] === 2
                    && $slow['files_per_minute'] === 0.05
                    && $slow['files_per_hour'] === 3.0;
            });

        Carbon::setTestNow();
    }

    public function test_month_shows_operator_day_and_week_averages(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-29 16:00:00', config('app.timezone')));

        $op = User::factory()->entry()->create(['name' => 'Break Op']);
        $category = CaseCategory::query()->where('code', 'CON')->firstOrFail();

        $this->seedStoredScan($op, $category, 'CON/2026/B001', '2026-10-28 09:00:00');
        $this->seedStoredScan($op, $category, 'CON/2026/B002', '2026-10-28 09:10:00');
        $this->seedStoredScan($op, $category, 'CON/2026/B003', '2026-10-28 13:00:00');
        $this->seedStoredScan($op, $category, 'CON/2026/B004', '2026-10-29 10:00:00');
        $this->seedStoredScan($op, $category, 'CON/2026/B005', '2026-10-29 10:20:00');

        $this->actingAs($op)
            ->get(route('entry.productivity.index', ['period' => 'month', 'month' => '2026-10']))
            ->assertOk()
            ->assertSee('Fail/hari', false)
            ->assertSee('Fail/minggu', false)
            ->assertViewHas('operators', function ($operators) {
                $row = collect($operators)->firstWhere('name', 'Break Op');

                // 5 files / 29 days → 0.2/hari; 5 × 7 / 29 → 1.2/minggu
                return $row
                    && $row['count'] === 5
                    && $row['files_per_day'] === 0.2
                    && $row['files_per_week'] === 1.2
                    && ! array_key_exists('files_per_minute', $row);
            });

        Carbon::setTestNow();
    }

    public function test_year_shows_operator_month_average(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-29 12:00:00', config('app.timezone')));

        $op = User::factory()->entry()->create(['name' => 'Year Op']);
        $category = CaseCategory::query()->where('code', 'CON')->firstOrFail();

        $this->seedStoredScan($op, $category, 'CON/2026/Y001', '2026-03-10 09:00:00');
        $this->seedStoredScan($op, $category, 'CON/2026/Y002', '2026-06-15 10:00:00');
        $this->seedStoredScan($op, $category, 'CON/2026/Y003', '2026-10-01 11:00:00');
        $this->seedStoredScan($op, $category, 'CON/2026/Y004', '2026-10-20 11:00:00');

        $this->actingAs($op)
            ->get(route('entry.productivity.index', ['period' => 'year', 'year' => 2026]))
            ->assertOk()
            ->assertSee('Fail/bulan', false)
            ->assertViewHas('registeredInPeriod', 4)
            ->assertViewHas('periodLabel', '2026')
            ->assertViewHas('operators', function ($operators) {
                $row = collect($operators)->firstWhere('name', 'Year Op');

                // 4 files / 10 months elapsed → 0.4/bulan
                return $row
                    && $row['count'] === 4
                    && $row['files_per_month'] === 0.4
                    && ! array_key_exists('files_per_week', $row);
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
