<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $permissionIds = [];

        foreach (['request', 'review'] as $action) {
            $name = "completions.{$action}";
            $permissionId = DB::table('permissions')->where('name', $name)->value('id');

            if (! $permissionId) {
                $permissionId = DB::table('permissions')->insertGetId([
                    'name' => $name,
                    'label' => Str::headline($action).' '.Str::headline('completions'),
                    'module' => 'completions',
                    'action' => $action,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $permissionIds[$action] = $permissionId;
        }

        $requestPositionSlugs = [
            'records-registry-manager',
            'senior-conveyancing-executive',
            'corporate-legal-executive',
        ];

        foreach ($requestPositionSlugs as $slug) {
            $positionId = DB::table('positions')->where('slug', $slug)->value('id');
            if (! $positionId) {
                continue;
            }

            DB::table('permission_position')->insertOrIgnore([
                'permission_id' => $permissionIds['request'],
                'position_id' => $positionId,
            ]);
        }

        $reviewPositionId = DB::table('positions')->where('slug', 'records-registry-manager')->value('id');
        if ($reviewPositionId) {
            DB::table('permission_position')->insertOrIgnore([
                'permission_id' => $permissionIds['review'],
                'position_id' => $reviewPositionId,
            ]);
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', ['completions.request', 'completions.review'])
            ->pluck('id');

        if ($permissionIds->isEmpty()) {
            return;
        }

        DB::table('permission_position')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
