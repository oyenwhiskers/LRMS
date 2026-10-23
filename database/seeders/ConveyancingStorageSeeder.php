<?php

namespace Database\Seeders;

use App\Models\Rack;
use App\Models\Shelf;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ConveyancingStorageSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/storage_layout.json');

        if (! is_file($path)) {
            throw new RuntimeException("Missing storage layout snapshot: {$path}");
        }

        /** @var array{racks: list<array{code: string, name: ?string, is_active: bool, shelves: list<array{code: string, name: ?string, is_active: bool, qr_identifier: ?string}>}>} $payload */
        $payload = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        DB::transaction(function () use ($payload): void {
            foreach ($payload['racks'] ?? [] as $rackData) {
                $rack = Rack::query()->firstOrCreate(
                    ['code' => $rackData['code']],
                    [
                        'name' => $rackData['name'] ?? null,
                        'is_active' => (bool) ($rackData['is_active'] ?? true),
                    ],
                );

                $expectedCodes = [];

                foreach ($rackData['shelves'] ?? [] as $shelfData) {
                    $code = $shelfData['code'];
                    $expectedCodes[] = $code;

                    $shelf = $rack->shelves()->where('code', $code)->first();

                    if ($shelf) {
                        $shelf->fill([
                            'name' => $shelfData['name'] ?? $shelf->name,
                            'is_active' => (bool) ($shelfData['is_active'] ?? true),
                        ])->save();

                        continue;
                    }

                    $shelf = new Shelf([
                        'rack_id' => $rack->id,
                        'code' => $code,
                        'name' => $shelfData['name'] ?? null,
                        'is_active' => (bool) ($shelfData['is_active'] ?? true),
                    ]);

                    if (! empty($shelfData['qr_identifier'])) {
                        $shelf->qr_identifier = $shelfData['qr_identifier'];
                    }

                    $shelf->save();
                }

                $rack->shelves()
                    ->whereNotIn('code', $expectedCodes)
                    ->whereDoesntHave('legalFiles')
                    ->delete();
            }
        });
    }
}
