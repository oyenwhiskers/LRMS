<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('lrms:cleanup-imports', function (): void {
    $deleted = 0;
    foreach (Storage::files('imports') as $file) {
        if (Storage::lastModified($file) < now()->subDay()->timestamp) {
            Storage::delete($file);
            $deleted++;
        }
    }
    $this->info("Deleted {$deleted} expired import file(s).");
})->purpose('Delete abandoned Excel import previews older than 24 hours');

Schedule::command('lrms:cleanup-imports')->dailyAt('02:00')->withoutOverlapping();
