<?php

namespace App\Domain\Registration;

use App\Models\Position;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterApplicant
{
    /**
     * @param  array{staff_number:string,email:string,password:string,requested_position_id:int}  $data
     */
    public function handle(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $staff = Staff::query()
                ->where('staff_number', $data['staff_number'])
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (! $staff || $staff->user()->exists()) {
                throw ValidationException::withMessages([
                    'staff_number' => 'The staff number cannot be used for registration.',
                ]);
            }

            $position = Position::query()
                ->whereKey($data['requested_position_id'])
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (! $position) {
                throw ValidationException::withMessages([
                    'requested_position_id' => 'The selected position is unavailable.',
                ]);
            }

            return User::query()->create([
                'staff_id' => $staff->id,
                'requested_position_id' => $position->id,
                'name' => $staff->full_name,
                'email' => strtolower($data['email']),
                'password' => $data['password'],
                'role' => User::ROLE_USER,
                'status' => User::STATUS_PENDING,
            ]);
        });
    }
}
