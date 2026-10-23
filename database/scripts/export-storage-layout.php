<?php

/**
 * Dump current racks/shelves into database/data/storage_layout.json for ConveyancingStorageSeeder.
 * Usage: php database/scripts/export-storage-layout.php
 */

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Rack;

$racks = Rack::query()
    ->with(['shelves' => fn ($q) => $q->orderBy('code')])
    ->orderBy('code')
    ->get()
    ->map(fn (Rack $rack) => [
        'code' => $rack->code,
        'name' => $rack->name,
        'is_active' => (bool) $rack->is_active,
        'shelves' => $rack->shelves->map(fn ($shelf) => [
            'code' => $shelf->code,
            'name' => $shelf->name,
            'is_active' => (bool) $shelf->is_active,
            'qr_identifier' => $shelf->qr_identifier,
        ])->values()->all(),
    ])
    ->values()
    ->all();

$payload = [
    'exported_at' => now()->toIso8601String(),
    'rack_count' => count($racks),
    'shelf_count' => collect($racks)->sum(fn ($r) => count($r['shelves'])),
    'racks' => $racks,
];

$out = database_path('data/storage_layout.json');
if (! is_dir(dirname($out))) {
    mkdir(dirname($out), 0755, true);
}

file_put_contents(
    $out,
    json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
);

echo "Wrote {$payload['rack_count']} racks / {$payload['shelf_count']} shelves to {$out}\n";
