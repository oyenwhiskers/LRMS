<?php

namespace Database\Seeders;

use App\Models\Rack;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ConveyancingStorageSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (['B', 'C', 'D'] as $rackCode) {
                $this->seedRack($rackCode, [
                    [range('A', 'C'), 6],
                    [range('D', 'X'), 5],
                ]);
            }

            $this->seedRack('E', [
                [range('A', 'I'), 5],
                [range('J', 'U'), 8],
                [array_merge(range('V', 'Z'), ['Z1']), 7],
            ]);
            $this->seedRack('F', [
                [range('A', 'F'), 5],
                [range('G', 'I'), 7],
                [range('J', 'Q'), 8],
                [range('R', 'T'), 7],
                [array_merge(range('U', 'Z'), ['Z1', 'Z2', 'Z3']), 8],
            ]);
            $this->seedRack('G', [[range('A', 'L'), 8]]);
        });
    }

    private function seedRack(string $rackCode, array $sections): void
    {
        $rack = Rack::firstOrCreate(
            ['code' => $rackCode],
            ['name' => 'Conveyancing', 'is_active' => true],
        );

        $expectedCodes = [];
        foreach ($sections as [$columns, $rowCount]) {
            foreach ($columns as $column) {
                foreach (range(1, $rowCount) as $row) {
                    $code = $column.$row;
                    $expectedCodes[] = $code;
                    $rack->shelves()->firstOrCreate(
                        ['code' => $code],
                        ['name' => "Column {$column}, Row {$row}", 'is_active' => true],
                    );
                }
            }
        }

        $rack->shelves()
            ->whereNotIn('code', $expectedCodes)
            ->whereDoesntHave('legalFiles')
            ->delete();
    }
}
