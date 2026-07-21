<?php

namespace Tests\Feature;

use App\Models\Position;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_approve_registration_and_change_authoritative_position(): void
    {
        $admin = User::factory()->admin()->create();
        $requested = Position::factory()->create();
        $approved = Position::factory()->create();
        $staff = Staff::factory()->create();
        $applicant = User::factory()->pending()->create([
            'staff_id' => $staff->id,
            'requested_position_id' => $requested->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.registrations.update', $applicant), [
                'decision' => 'approve',
                'position_id' => $approved->id,
            ])
            ->assertRedirect();

        $applicant->refresh();
        $this->assertSame(User::STATUS_APPROVED, $applicant->status);
        $this->assertSame($admin->id, $applicant->reviewed_by);
        $this->assertNotNull($applicant->reviewed_at);
        $this->assertSame($approved->id, $staff->fresh()->position_id);
    }

    public function test_admin_can_reject_registration_with_feedback(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = Staff::factory()->create();
        $applicant = User::factory()->pending()->create(['staff_id' => $staff->id]);

        $this->actingAs($admin)
            ->patch(route('admin.registrations.update', $applicant), [
                'decision' => 'reject',
                'rejection_reason' => 'Staff record requires HR verification.',
            ])
            ->assertRedirect();

        $applicant->refresh();
        $this->assertSame(User::STATUS_REJECTED, $applicant->status);
        $this->assertSame('Staff record requires HR verification.', $applicant->rejection_reason);
        $this->assertNull($staff->fresh()->position_id);
    }

    public function test_review_requires_action_permission_and_a_pending_registration(): void
    {
        $reviewer = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $position = Position::factory()->create();
        $applicant = User::factory()->pending()->create(['staff_id' => Staff::factory()->create()->id]);

        $payload = ['decision' => 'approve', 'position_id' => $position->id];

        $this->actingAs($reviewer)
            ->patch(route('admin.registrations.update', $applicant), $payload)
            ->assertForbidden();

        $applicant->update(['status' => User::STATUS_REJECTED]);

        $this->actingAs($admin)
            ->patch(route('admin.registrations.update', $applicant), $payload)
            ->assertSessionHasErrors('decision');
    }
}
