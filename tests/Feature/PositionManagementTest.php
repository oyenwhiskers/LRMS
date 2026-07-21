<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Position;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PositionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_position_with_action_permissions(): void
    {
        $admin = User::factory()->admin()->create();
        $permissions = Permission::factory()->count(2)->create();

        $this->actingAs($admin)
            ->post(route('admin.positions.store'), [
                'name' => 'Senior Legal Officer',
                'slug' => 'senior-legal-officer',
                'description' => 'Reviews complex legal matters.',
                'is_active' => true,
                'permissions' => $permissions->modelKeys(),
            ])
            ->assertRedirect(route('admin.positions.index'));

        $position = Position::query()->where('slug', 'senior-legal-officer')->firstOrFail();
        $this->assertEqualsCanonicalizing($permissions->modelKeys(), $position->permissions()->pluck('permissions.id')->all());
    }

    public function test_position_permissions_control_each_management_action(): void
    {
        $view = Permission::factory()->create([
            'name' => 'positions.view',
            'module' => 'positions',
            'action' => 'view',
        ]);
        $position = Position::factory()->create();
        $position->permissions()->attach($view);
        $staff = Staff::factory()->create(['position_id' => $position->id]);
        $user = User::factory()->create(['staff_id' => $staff->id]);
        $target = Position::factory()->create();

        $this->actingAs($user)->get(route('admin.positions.index'))->assertOk();
        $this->actingAs($user)->get(route('admin.positions.edit', $target))->assertForbidden();
        $this->actingAs($user)->delete(route('admin.positions.destroy', $target))->assertForbidden();
    }

    public function test_assigned_position_is_deactivated_without_losing_history(): void
    {
        $admin = User::factory()->admin()->create();
        $position = Position::factory()->create();
        Staff::factory()->create(['position_id' => $position->id]);

        $this->actingAs($admin)
            ->delete(route('admin.positions.destroy', $position))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('positions', ['id' => $position->id, 'is_active' => false]);
        $this->assertDatabaseHas('staff', ['position_id' => $position->id]);
    }
}
