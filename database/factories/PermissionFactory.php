<?php

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $module = fake()->unique()->word();
        $action = fake()->randomElement(['view', 'create', 'update', 'delete']);

        return [
            'name' => "{$module}.{$action}",
            'label' => ucfirst($action).' '.ucfirst($module),
            'module' => $module,
            'action' => $action,
        ];
    }
}
