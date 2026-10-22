<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $permissionId = DB::table('permissions')->where('name', 'files.delete')->value('id');

        if (! $permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => 'files.delete',
                'label' => Str::headline('delete').' '.Str::headline('files'),
                'module' => 'files',
                'action' => 'delete',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $positionId = DB::table('positions')->where('slug', 'records-registry-manager')->value('id');
        if (! $positionId) {
            return;
        }

        DB::table('permission_position')->insertOrIgnore([
            'permission_id' => $permissionId,
            'position_id' => $positionId,
        ]);
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'files.delete')->value('id');
        if (! $permissionId) {
            return;
        }

        DB::table('permission_position')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
    }
};
