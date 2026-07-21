<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Position;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_bypasses_all_catalog_permission_checks(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('files.archive'));
        $this->assertTrue(Gate::forUser($admin)->allows('positions.view'));

        $this->actingAs($admin)->get(route('dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('admin.positions.index'))->assertOk();
    }

    public function test_user_inherits_permissions_only_from_active_staff_position(): void
    {
        $permission = Permission::factory()->create([
            'name' => 'files.update',
            'module' => 'files',
            'action' => 'update',
        ]);
        $position = Position::factory()->create();
        $position->permissions()->attach($permission);
        $staff = Staff::factory()->create(['position_id' => $position->id]);
        $user = User::factory()->create(['staff_id' => $staff->id]);

        $this->assertTrue(Gate::forUser($user)->allows('files.update'));
        $this->assertFalse(Gate::forUser($user)->allows('files.archive'));

        $position->update(['is_active' => false]);

        $this->assertFalse(Gate::forUser($user->fresh())->allows('files.update'));
    }

    public function test_pending_user_never_receives_position_permissions(): void
    {
        $permission = Permission::factory()->create([
            'name' => 'staff.view',
            'module' => 'staff',
            'action' => 'view',
        ]);
        $position = Position::factory()->create();
        $position->permissions()->attach($permission);
        $staff = Staff::factory()->create(['position_id' => $position->id]);
        $user = User::factory()->pending()->create(['staff_id' => $staff->id]);

        $this->assertFalse(Gate::forUser($user)->allows('staff.view'));
    }
}
