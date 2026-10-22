<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('legal_files')
            ->where('status', 'pending_initial_storage')
            ->update([
                'status' => 'registered',
                'updated_at' => now(),
            ]);

        DB::table('file_movements')
            ->where('new_status', 'pending_initial_storage')
            ->update([
                'new_status' => 'registered',
                'updated_at' => now(),
            ]);

        DB::table('file_movements')
            ->where('previous_status', 'pending_initial_storage')
            ->update([
                'previous_status' => 'registered',
                'updated_at' => now(),
            ]);

        DB::table('legal_files')
            ->where('status', 'archived')
            ->update([
                'status' => 'stored',
                'archived_at' => null,
                'updated_at' => now(),
            ]);

        DB::table('legal_files')
            ->whereNotNull('archived_at')
            ->update([
                'archived_at' => null,
                'updated_at' => now(),
            ]);

        DB::table('file_movements')
            ->where('new_status', 'archived')
            ->update([
                'new_status' => 'stored',
                'updated_at' => now(),
            ]);

        DB::table('file_movements')
            ->where('previous_status', 'archived')
            ->update([
                'previous_status' => 'stored',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('legal_files')
            ->where('status', 'registered')
            ->update([
                'status' => 'pending_initial_storage',
                'updated_at' => now(),
            ]);

        DB::table('file_movements')
            ->where('new_status', 'registered')
            ->update([
                'new_status' => 'pending_initial_storage',
                'updated_at' => now(),
            ]);

        DB::table('file_movements')
            ->where('previous_status', 'registered')
            ->update([
                'previous_status' => 'pending_initial_storage',
                'updated_at' => now(),
            ]);
    }
};
