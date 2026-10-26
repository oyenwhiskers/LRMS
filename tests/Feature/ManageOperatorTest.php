<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureManageOperatorUnlocked;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ManageOperatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_entry_user_needs_password_then_can_reset_operator_password(): void
    {
        config(['lrms.manage_operator_password' => 'YKN@2026']);

        $entry = User::factory()->entry()->create();
        $operator = User::factory()->entry()->create([
            'password' => Hash::make('Old!Password123'),
        ]);

        $this->actingAs($entry)
            ->get(route('entry.manage-operators.index'))
            ->assertRedirect(route('entry.manage-operators.unlock'));

        $this->actingAs($entry)
            ->from(route('entry.manage-operators.unlock'))
            ->post(route('entry.manage-operators.unlock.store'), ['password' => 'wrong'])
            ->assertSessionHasErrors('password');

        $this->actingAs($entry)
            ->post(route('entry.manage-operators.unlock.store'), ['password' => 'YKN@2026'])
            ->assertRedirect(route('entry.manage-operators.index'));

        $this->assertSame($entry->id, session(EnsureManageOperatorUnlocked::SESSION_KEY));

        $this->actingAs($entry)
            ->get(route('entry.manage-operators.index'))
            ->assertOk()
            ->assertSee('Urus Operator', false)
            ->assertSee($operator->email, false);

        $this->actingAs($entry)
            ->from(route('entry.manage-operators.password.edit', $operator))
            ->put(route('entry.manage-operators.password.update', $operator), [
                'password' => 'weak',
                'password_confirmation' => 'different',
            ])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('Old!Password123', $operator->fresh()->password));

        $this->actingAs($entry)
            ->put(route('entry.manage-operators.password.update', $operator), [
                'password' => 'New!Password456',
                'password_confirmation' => 'New!Password456',
            ])
            ->assertRedirect(route('entry.manage-operators.index'))
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('New!Password456', $operator->fresh()->password));
    }

    public function test_cannot_reset_password_for_non_entry_user(): void
    {
        config(['lrms.manage_operator_password' => 'YKN@2026']);

        $entry = User::factory()->entry()->create();
        $other = User::factory()->create();

        $this->actingAs($entry)
            ->post(route('entry.manage-operators.unlock.store'), ['password' => 'YKN@2026']);

        $this->actingAs($entry)
            ->get(route('entry.manage-operators.password.edit', $other))
            ->assertNotFound();

        $this->actingAs($entry)
            ->put(route('entry.manage-operators.password.update', $other), [
                'password' => 'New!Password456',
                'password_confirmation' => 'New!Password456',
            ])
            ->assertNotFound();
    }

    public function test_firm_user_cannot_open_manage_operators(): void
    {
        $firm = User::factory()->create();

        $this->actingAs($firm)->get(route('entry.manage-operators.unlock'))->assertForbidden();
        $this->actingAs($firm)->get(route('entry.manage-operators.index'))->assertForbidden();
    }

    public function test_unlock_clears_on_logout(): void
    {
        config(['lrms.manage_operator_password' => 'YKN@2026']);

        $entry = User::factory()->entry()->create();

        $this->actingAs($entry)
            ->post(route('entry.manage-operators.unlock.store'), ['password' => 'YKN@2026'])
            ->assertRedirect(route('entry.manage-operators.index'));

        $this->actingAs($entry)->post(route('logout'))->assertRedirect(route('entry.login'));
        $this->assertGuest();
        $this->assertNull(session(EnsureManageOperatorUnlocked::SESSION_KEY));

        $this->actingAs($entry)
            ->get(route('entry.manage-operators.index'))
            ->assertRedirect(route('entry.manage-operators.unlock'));
    }
}
