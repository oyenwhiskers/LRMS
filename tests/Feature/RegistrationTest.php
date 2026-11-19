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

    public function test_applicant_can_submit_a_pending_registration(): void
    {
        $position = Position::factory()->create();

        $response = $this->post(route('register'), [
            'full_name' => 'Amina Counsel',
            'email' => 'amina@example.test',
            'requested_position_id' => $position->id,
            'password' => 'Strong!Pass123',
            'password_confirmation' => 'Strong!Pass123',
        ]);

        $user = User::query()->where('email', 'amina@example.test')->firstOrFail();
        $staff = $user->staff;

        $response->assertRedirect(route('account.status'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame(User::STATUS_PENDING, $user->status);
        $this->assertNotNull($staff);
        $this->assertSame($position->id, $user->requested_position_id);
        $this->assertSame('Amina Counsel', $user->name);
        $this->assertSame('Amina Counsel', $staff->full_name);
        $this->assertSame('amina@example.test', $staff->email);
        $this->assertNull($staff->position_id);
        $this->assertStringStartsWith('REG-', $staff->staff_number);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        $position = Position::factory()->create();
        User::factory()->create(['email' => 'taken@example.test']);

        $this->post(route('register'), [
            'full_name' => 'Duplicate Applicant',
            'email' => 'taken@example.test',
            'requested_position_id' => $position->id,
            'password' => 'Strong!Pass123',
            'password_confirmation' => 'Strong!Pass123',
        ])->assertSessionHasErrors('email');
    }

    public function test_registration_requires_a_strong_confirmed_password_and_active_position(): void
    {
        $inactivePosition = Position::factory()->inactive()->create();

        $this->post(route('register'), [
            'full_name' => 'New Applicant',
            'email' => 'new@example.test',
            'requested_position_id' => $inactivePosition->id,
            'password' => 'weak',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors(['requested_position_id', 'password']);

        $this->assertDatabaseMissing('users', ['email' => 'new@example.test']);
    }
}
