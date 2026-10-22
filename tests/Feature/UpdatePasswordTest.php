<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UpdatePasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_user_can_update_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Old!Password123'),
        ]);

        $this->actingAs($user)
            ->from(route('account.password.edit'))
            ->put(route('account.password.update'), [
                'current_password' => 'Old!Password123',
                'password' => 'New!Password456',
                'password_confirmation' => 'New!Password456',
            ])
            ->assertRedirect(route('account.password.edit'))
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('New!Password456', $user->fresh()->password));
    }

    public function test_password_update_requires_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Old!Password123'),
        ]);

        $this->actingAs($user)
            ->from(route('account.password.edit'))
            ->put(route('account.password.update'), [
                'current_password' => 'Wrong!Password123',
                'password' => 'New!Password456',
                'password_confirmation' => 'New!Password456',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('Old!Password123', $user->fresh()->password));
    }
}
