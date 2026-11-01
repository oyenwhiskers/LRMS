<?php

namespace App\Domain\Entry;

use App\Models\EntryScan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetEntryScanReviewStatus
{
    public function handle(EntryScan $scan, User $user, string $reviewStatus, ?string $notes = null): EntryScan
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

        $notes = is_string($notes) ? trim($notes) : null;
        if ($notes === '') {
            $notes = null;
        }

        return DB::transaction(function () use ($scan, $user, $reviewStatus, $notes): EntryScan {
            $locked = EntryScan::query()->whereKey($scan->id)->lockForUpdate()->firstOrFail();

            if ($reviewStatus === EntryScan::REVIEW_PENDING) {
                // Keep review_notes + review_comment_log so QC still sees prior comments after resubmit.
                $locked->update([
                    'review_status' => EntryScan::REVIEW_PENDING,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ]);
            } elseif ($reviewStatus === EntryScan::REVIEW_NEEDS_CORRECTION) {
                $log = is_array($locked->review_comment_log) ? $locked->review_comment_log : [];
                $log[] = [
                    'note' => $notes,
                    'by' => $user->id,
                    'by_name' => $user->name,
                    'at' => now()->utc()->format('Y-m-d H:i:s'),
                ];
                $locked->update([
                    'review_status' => $reviewStatus,
                    'reviewed_by' => $user->id,
                    'reviewed_at' => now(),
                    'review_notes' => $notes,
                    'review_comment_log' => $log,
                ]);
            } else {
                // Clear active note; keep comment log for history.
                $locked->update([
                    'review_status' => $reviewStatus,
                    'reviewed_by' => $user->id,
                    'reviewed_at' => now(),
                    'review_notes' => null,
                ]);
            }

            return $locked->fresh();
        });
    }
}
