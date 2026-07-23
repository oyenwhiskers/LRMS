<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_files', function (Blueprint $table): void {
            $table->index(['archived_at', 'status'], 'legal_files_active_status_index');
            $table->index(['archived_at', 'created_at'], 'legal_files_active_created_index');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE legal_files ADD FULLTEXT legal_files_search_fulltext '.
                '(reference_number, loan_reference, purchaser, vendor, property)'
            );
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE legal_files DROP INDEX legal_files_search_fulltext');
        }

        Schema::table('legal_files', function (Blueprint $table): void {
            $table->dropIndex('legal_files_active_status_index');
            $table->dropIndex('legal_files_active_created_index');
        });
    }
};
