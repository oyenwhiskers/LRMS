<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('legal_files')
            ->where('status', 'returned_pending_storage')
            ->update([
                'status' => 'stored',
                'updated_at' => now(),
            ]);

        DB::table('file_movements')
            ->where('new_status', 'returned_pending_storage')
            ->update([
                'new_status' => 'stored',
                'updated_at' => now(),
            ]);

        DB::table('file_movements')
            ->where('previous_status', 'returned_pending_storage')
            ->update([
                'previous_status' => 'stored',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('file_movements')
            ->where('type', 'return')
            ->where('new_status', 'stored')
            ->update([
                'new_status' => 'returned_pending_storage',
                'updated_at' => now(),
            ]);
    }
};
