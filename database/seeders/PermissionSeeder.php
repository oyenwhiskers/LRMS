<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (config('permissions.catalog', []) as $module => $actions) {
            foreach ($actions as $action) {
                Permission::query()->updateOrCreate(
                    ['name' => "{$module}.{$action}"],
                    [
                        'label' => Str::headline($action).' '.Str::headline($module),
                        'module' => $module,
                        'action' => $action,
                    ],
                );
            }
        }
    }
}
