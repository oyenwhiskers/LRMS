<?php

namespace Tests\Feature;

use App\Models\Position;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_page_lists_active_positions_without_revealing_staff_details(): void
    {
        $active = Position::factory()->create(['name' => 'Legal Officer']);
        $inactive = Position::factory()->inactive()->create(['name' => 'Retired Position']);
        $staff = Staff::factory()->create(['full_name' => 'Confidential Staff Name']);

        $this->get(route('register'))
            ->assertOk()
            ->assertSee($active->name)
            ->assertDontSee($inactive->name)
            ->assertDontSee($staff->full_name)
            ->assertDontSee($staff->staff_number);
    }

    public function test_eligible_staff_can_submit_a_pending_registration(): void
    {
        $position = Position::factory()->create();
        $staff = Staff::factory()->create(['full_name' => 'Amina Counsel']);

        $response = $this->post(route('register'), [
            'staff_number' => $staff->staff_number,
            'email' => 'amina@example.test',
            'requested_position_id' => $position->id,
            'password' => 'Strong!Pass123',
            'password_confirmation' => 'Strong!Pass123',
        ]);

        $user = User::query()->where('email', 'amina@example.test')->firstOrFail();

        $response->assertRedirect(route('account.status'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame(User::STATUS_PENDING, $user->status);
        $this->assertSame($staff->id, $user->staff_id);
        $this->assertSame($position->id, $user->requested_position_id);
        $this->assertSame('Amina Counsel', $user->name);
        $this->assertNull($staff->fresh()->position_id);
    }

    public function test_registration_rejects_inactive_staff_and_staff_with_an_account(): void
    {
        $position = Position::factory()->create();
        $inactiveStaff = Staff::factory()->inactive()->create();
        $linkedStaff = Staff::factory()->create();
        User::factory()->create(['staff_id' => $linkedStaff->id]);

        foreach ([$inactiveStaff, $linkedStaff] as $staff) {
            $this->post(route('register'), [
                'staff_number' => $staff->staff_number,
                'email' => fake()->unique()->safeEmail(),
                'requested_position_id' => $position->id,
                'password' => 'Strong!Pass123',
                'password_confirmation' => 'Strong!Pass123',
            ])->assertSessionHasErrors('staff_number');
        }
    }

    public function test_registration_requires_a_strong_confirmed_password_and_active_position(): void
    {
        $staff = Staff::factory()->create();
        $inactivePosition = Position::factory()->inactive()->create();

        $this->post(route('register'), [
            'staff_number' => $staff->staff_number,
            'email' => 'new@example.test',
            'requested_position_id' => $inactivePosition->id,
            'password' => 'weak',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors(['requested_position_id', 'password']);

        $this->assertDatabaseMissing('users', ['email' => 'new@example.test']);
    }
}
