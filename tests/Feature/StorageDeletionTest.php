<?php

namespace Tests\Feature;

use App\Models\LegalFile;
use App\Models\Rack;
use App\Models\Shelf;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StorageDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_delete_an_unused_shelf_and_a_rack_with_unused_shelves(): void
    {
        $admin = User::factory()->admin()->create();
        $rack = Rack::create(['code' => 'A1', 'name' => 'Rack A1']);
        $first = Shelf::create(['rack_id' => $rack->id, 'code' => 'S1', 'name' => 'Shelf 1']);
        $second = Shelf::create(['rack_id' => $rack->id, 'code' => 'S2', 'name' => 'Shelf 2']);

        $this->actingAs($admin)->get(route('storage.racks.show', $rack))
            ->assertOk()
            ->assertSee('data-modal="delete-rack"', false)
            ->assertSee('data-modal="delete-shelf-'.$first->id.'"', false);

        $this->actingAs($admin)->delete(route('storage.shelves.destroy', $first))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Shelf deleted.');
        $this->assertModelMissing($first);

        $this->actingAs($admin)->delete(route('storage.racks.destroy', $rack))
            ->assertRedirect(route('storage.index'))
            ->assertSessionHasNoErrors();
        $this->assertModelMissing($rack);
        $this->assertModelMissing($second);
    }

    public function test_shelf_and_rack_deletion_are_blocked_while_a_file_is_linked(): void
    {
        $admin = User::factory()->admin()->create();
        $rack = Rack::create(['code' => 'A1', 'name' => 'Rack A1']);
        $shelf = Shelf::create(['rack_id' => $rack->id, 'code' => 'S1', 'name' => 'Shelf 1']);
        $file = $this->file($admin, $shelf);

        $this->actingAs($admin)->delete(route('storage.shelves.destroy', $shelf))
            ->assertSessionHasErrors('shelf');
        $this->actingAs($admin)->delete(route('storage.racks.destroy', $rack))
            ->assertSessionHasErrors('rack');
        $this->assertModelExists($rack);
        $this->assertModelExists($shelf);
        $this->assertSame($shelf->id, $file->fresh()->shelf_id);
    }

    public function test_movement_history_prevents_deletion_even_after_a_file_moves_away(): void
    {
        $admin = User::factory()->admin()->create();
        $rack = Rack::create(['code' => 'A1', 'name' => 'Rack A1']);
        $shelf = Shelf::create(['rack_id' => $rack->id, 'code' => 'S1', 'name' => 'Shelf 1']);
        $file = $this->file($admin);
        DB::table('file_movements')->insert([
            'legal_file_id' => $file->id,
            'type' => 'store',
            'processed_by' => $admin->id,
            'shelf_id' => $shelf->id,
            'new_status' => LegalFile::STATUS_STORED,
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->delete(route('storage.shelves.destroy', $shelf))
            ->assertSessionHasErrors('shelf');
        $this->actingAs($admin)->delete(route('storage.racks.destroy', $rack))
            ->assertSessionHasErrors('rack');
        $this->assertModelExists($shelf);
    }

    public function test_users_without_storage_management_cannot_delete_locations(): void
    {
        $user = User::factory()->create();
        $rack = Rack::create(['code' => 'A1', 'name' => 'Rack A1']);
        $shelf = Shelf::create(['rack_id' => $rack->id, 'code' => 'S1', 'name' => 'Shelf 1']);

        $this->actingAs($user)->delete(route('storage.shelves.destroy', $shelf))->assertForbidden();
        $this->actingAs($user)->delete(route('storage.racks.destroy', $rack))->assertForbidden();
        $this->assertModelExists($rack);
        $this->assertModelExists($shelf);
    }

    private function file(User $admin, ?Shelf $shelf = null): LegalFile
    {
        return LegalFile::create([
            'reference_number' => 'CON/2026/9999',
            'matter_type' => 'Conveyancing',
            'purchaser' => 'Sample purchaser',
            'property' => 'Sample property',
            'shelf_id' => $shelf?->id,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
    }
}
