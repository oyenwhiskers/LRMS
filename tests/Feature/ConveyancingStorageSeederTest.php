<?php

namespace Tests\Feature;

use App\Models\Rack;
use App\Models\Shelf;
use App\Models\User;
use Database\Seeders\ConveyancingStorageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConveyancingStorageSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_conveyancing_racks_have_the_complete_grid_and_print_compact_sticker_codes(): void
    {
        $this->seed(ConveyancingStorageSeeder::class);

        $expectedCodes = [];
        foreach ([
            [range('A', 'C'), 6],
            [range('D', 'X'), 5],
        ] as [$columns, $rowCount]) {
            foreach ($columns as $column) {
                foreach (range(1, $rowCount) as $row) {
                    $expectedCodes[] = $column.$row;
                }
            }
        }
        sort($expectedCodes);

        foreach (['B', 'C', 'D'] as $rackCode) {
            $rack = Rack::where('code', $rackCode)->firstOrFail();
            $this->assertSame('Conveyancing', $rack->name);
            $this->assertSame($expectedCodes, $rack->shelves()->orderBy('code')->pluck('code')->all());
            $this->assertCount(123, $expectedCodes);
            $this->assertFalse($rack->shelves()->where('code', 'D6')->exists());
            $this->assertTrue($rack->shelves()->where('code', 'C6')->exists());
        }
        $this->assertSame(183, Rack::where('code', 'E')->firstOrFail()->shelves()->count());
        $rackF = Rack::where('code', 'F')->firstOrFail();
        $expectedFCodes = [];
        foreach ([
            [range('A', 'F'), 5],
            [range('G', 'I'), 7],
            [range('J', 'Q'), 8],
            [range('R', 'T'), 7],
            [array_merge(range('U', 'Z'), ['Z1', 'Z2', 'Z3']), 8],
        ] as [$columns, $rowCount]) {
            foreach ($columns as $column) {
                foreach (range(1, $rowCount) as $row) {
                    $expectedFCodes[] = $column.$row;
                }
            }
        }
        sort($expectedFCodes);
        $this->assertSame('Conveyancing', $rackF->name);
        $this->assertSame($expectedFCodes, $rackF->shelves()->orderBy('code')->pluck('code')->all());
        $this->assertCount(208, $expectedFCodes);
        $this->assertSame(96, Rack::where('code', 'G')->firstOrFail()->shelves()->count());
        $this->assertTrue(Shelf::whereHas('rack', fn ($query) => $query->where('code', 'E'))->where('code', 'Z17')->exists());
        $this->assertFalse(Shelf::whereHas('rack', fn ($query) => $query->where('code', 'E'))->where('code', 'Z18')->exists());
        $this->assertSame(6, Rack::count());
        $this->assertSame(856, Shelf::count());

        $first = Shelf::whereHas('rack', fn ($query) => $query->where('code', 'B'))
            ->where('code', 'A1')->firstOrFail();
        $identifier = $first->qr_identifier;
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('storage.shelves.label', $first))
            ->assertOk()
            ->assertSee('<div class="shelf-label__code">B/A1</div>', false);

        $this->seed(ConveyancingStorageSeeder::class);
        $this->assertSame(856, Shelf::count());
        $this->assertSame($identifier, $first->fresh()->qr_identifier);
    }
}
