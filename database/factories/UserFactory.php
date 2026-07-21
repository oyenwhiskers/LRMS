<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => User::ROLE_USER,
            'status' => User::STATUS_APPROVED,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (): array => ['status' => User::STATUS_PENDING]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => User::STATUS_REJECTED,
            'rejection_reason' => 'The request could not be verified.',
            'reviewed_at' => now(),
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (): array => [
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_APPROVED,
            'staff_id' => null,
        ]);
    }
}
