<?php

namespace App\Domain\Registration;

use App\Models\Position;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RegisterApplicant
{
    /**
     * @param  array{full_name:string,email:string,password:string,requested_position_id:int}  $data
     */
    public function handle(array $data): User
    {
        return DB::transaction(function () use ($data): User {
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

            $email = strtolower($data['email']);
            $fullName = trim($data['full_name']);

            $staff = Staff::query()->create([
                // ponytail: auto ID when firm has no staff numbers; assign real ones in Staff admin if needed
                'staff_number' => 'REG-'.strtoupper(Str::random(8)),
                'full_name' => $fullName,
                'email' => $email,
                'position_id' => null,
                'is_active' => true,
            ]);

            return User::query()->create([
                'staff_id' => $staff->id,
                'requested_position_id' => $position->id,
                'name' => $fullName,
                'email' => $email,
                'password' => $data['password'],
                'role' => User::ROLE_USER,
                'status' => User::STATUS_PENDING,
            ]);
        });
    }
}
