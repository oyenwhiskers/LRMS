<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DevelopmentAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $name = config('lrms.development_admin.name');
        $email = config('lrms.development_admin.email');
        $password = config('lrms.development_admin.password');

        if (! $name || ! $email || ! $password) {
            $this->command?->warn('Development admin not seeded; LRMS_ADMIN_* values are incomplete.');

            return;
        }

        User::query()->updateOrCreate(
            ['email' => strtolower($email)],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'role' => User::ROLE_ADMIN,
                'status' => User::STATUS_APPROVED,
                'reviewed_at' => now(),
            ],
        );
    }
}
