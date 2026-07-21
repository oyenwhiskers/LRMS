<?php

namespace App\Domain\Registration;

use App\Models\Position;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewRegistration
{
    /**
     * @param  array{decision:string,position_id?:int|null,rejection_reason?:string|null}  $data
     */
    public function handle(User $applicant, User $reviewer, array $data): User
    {
        return DB::transaction(function () use ($applicant, $reviewer, $data): User {
            $applicant = User::query()
                ->with('staff')
                ->lockForUpdate()
                ->findOrFail($applicant->id);

            if ($applicant->status !== User::STATUS_PENDING || ! $applicant->staff) {
                throw ValidationException::withMessages([
                    'decision' => 'This registration is no longer pending.',
                ]);
            }

            if ($data['decision'] === 'approve') {
                $position = Position::query()
                    ->whereKey($data['position_id'])
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();

                if (! $position) {
                    throw ValidationException::withMessages([
                        'position_id' => 'The selected position is unavailable.',
                    ]);
                }

                $applicant->staff->update(['position_id' => $position->id]);
                $applicant->status = User::STATUS_APPROVED;
                $applicant->rejection_reason = null;
            } else {
                $applicant->status = User::STATUS_REJECTED;
                $applicant->rejection_reason = $data['rejection_reason'];
            }

            $applicant->reviewed_at = now();
            $applicant->reviewed_by = $reviewer->id;
            $applicant->save();

            return $applicant->refresh();
        });
    }
}
