<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Position;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_account_can_sign_in_but_only_sees_pending_feedback(): void
    {
        $user = User::factory()->pending()->create(['password' => 'password']);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('account.status'));

        $this->assertAuthenticatedAs($user);
        $this->get(route('dashboard'))->assertRedirect(route('account.status'));
        $this->get(route('account.status'))->assertOk()->assertSee('Your request is pending');
    }

    public function test_rejected_account_sees_rejection_feedback_without_app_access(): void
    {
        $user = User::factory()->rejected()->create([
            'password' => 'password',
            'rejection_reason' => 'Contact Human Resources.',
        ]);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('account.status'));

        $this->get(route('account.status'))->assertSee('Contact Human Resources.');
        $this->get(route('dashboard'))->assertRedirect(route('account.status'));
    }

    public function test_approved_account_with_permission_can_access_and_logout(): void
    {
        $permission = Permission::factory()->create([
            'name' => 'dashboard.view',
            'module' => 'dashboard',
            'action' => 'view',
        ]);
        $position = Position::factory()->create();
        $position->permissions()->attach($permission);
        $staff = Staff::factory()->create(['position_id' => $position->id]);
        $user = User::factory()->create(['staff_id' => $staff->id, 'password' => 'password']);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))->assertOk();
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'incorrect',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
