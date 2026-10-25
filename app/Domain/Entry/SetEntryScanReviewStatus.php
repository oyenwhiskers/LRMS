<?php

namespace App\Domain\Entry;

use App\Models\EntryScan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetEntryScanReviewStatus
{
    public function handle(EntryScan $scan, User $user, string $reviewStatus): EntryScan
    {
        if (! in_array($reviewStatus, EntryScan::reviewStatuses(), true)) {
            throw ValidationException::withMessages([
                'review_status' => 'Status semakan tidak sah.',
            ]);
        }

        if (! in_array($scan->status, [EntryScan::STATUS_SAVED, EntryScan::STATUS_STORED], true)) {
            throw ValidationException::withMessages([
                'review_status' => 'Hanya fail yang sudah disimpan boleh disemak.',
            ]);
        }

        return DB::transaction(function () use ($scan, $user, $reviewStatus): EntryScan {
            $locked = EntryScan::query()->whereKey($scan->id)->lockForUpdate()->firstOrFail();

            if ($reviewStatus === EntryScan::REVIEW_PENDING) {
                $locked->update([
                    'review_status' => EntryScan::REVIEW_PENDING,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ]);
            } else {
                $locked->update([
                    'review_status' => $reviewStatus,
                    'reviewed_by' => $user->id,
                    'reviewed_at' => now(),
                ]);
            }

            return $locked->fresh();
        });
    }
}
