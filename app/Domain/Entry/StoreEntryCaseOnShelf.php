<?php

namespace App\Domain\Entry;

use App\Domain\Storage\ResolveShelfFromQr;
use App\Models\EntryScan;
use App\Models\FileMovement;
use App\Models\LegalFile;
use App\Models\Shelf;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StoreEntryCaseOnShelf
{
    public function __construct(private readonly ResolveShelfFromQr $resolveShelf) {}

    public function handle(EntryScan $scan, User $user, ?string $shelfQr = null, ?int $shelfId = null): EntryScan
    {
        if (! $scan->isOwnedBy($user) && ! $user->isAdmin()) {
            abort(403);
        }

        $field = $shelfId ? 'shelf_id' : 'shelf_qr';

        if ($scan->status !== EntryScan::STATUS_SAVED || ! $scan->legal_file_id) {
            throw ValidationException::withMessages([
                $field => 'Simpan kes dahulu sebelum mengimbas rak.',
            ]);
        }

        $shelf = $shelfId
            ? $this->resolveActiveShelf($shelfId)
            : $this->resolveShelf->handle($shelfQr);

        return DB::transaction(function () use ($scan, $user, $shelf, $field): EntryScan {
            $file = LegalFile::query()->whereKey($scan->legal_file_id)->lockForUpdate()->firstOrFail();

            if ($file->status !== LegalFile::STATUS_REGISTERED) {
                throw ValidationException::withMessages([
                    $field => "{$file->reference_number} tidak boleh disimpan pada status semasa.",
                ]);
            }

            FileMovement::create([
                'legal_file_id' => $file->id,
                'type' => 'stored',
                'processed_by' => $user->id,
                'shelf_id' => $shelf->id,
                'previous_status' => $file->status,
                'new_status' => LegalFile::STATUS_STORED,
                'occurred_at' => now(),
                'notes' => 'Stored via entry scan shortcut.',
            ]);

            $file->update([
                'status' => LegalFile::STATUS_STORED,
                'shelf_id' => $shelf->id,
                'current_holder_id' => null,
                'updated_by' => $user->id,
            ]);

            $scan->update([
                'shelf_id' => $shelf->id,
                'status' => EntryScan::STATUS_STORED,
            ]);

            return $scan->fresh(['legalFile', 'shelf.rack']);
        });
    }

    private function resolveActiveShelf(int $shelfId): Shelf
    {
        $shelf = Shelf::with('rack')->whereKey($shelfId)->first();
        if (! $shelf || ! $shelf->is_active || ! $shelf->rack?->is_active) {
            throw ValidationException::withMessages([
                'shelf_id' => 'Rak tidak sah atau tidak aktif.',
            ]);
        }

        return $shelf;
    }
}
