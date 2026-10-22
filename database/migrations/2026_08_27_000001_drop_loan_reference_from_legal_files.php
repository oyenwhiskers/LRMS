<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('legal_files', 'loan_reference')) {
            return;
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            $indexExists = collect(DB::select("SHOW INDEX FROM legal_files WHERE Key_name = 'legal_files_search_fulltext'"))->isNotEmpty();
            if ($indexExists) {
                DB::statement('ALTER TABLE legal_files DROP INDEX legal_files_search_fulltext');
            }
        }

        Schema::table('legal_files', function (Blueprint $table): void {
            $table->dropIndex(['loan_reference']);
            $table->dropColumn('loan_reference');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE legal_files ADD FULLTEXT legal_files_search_fulltext '.
                '(reference_number, purchaser, vendor, property)'
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('legal_files', 'loan_reference')) {
            return;
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            $indexExists = collect(DB::select("SHOW INDEX FROM legal_files WHERE Key_name = 'legal_files_search_fulltext'"))->isNotEmpty();
            if ($indexExists) {
                DB::statement('ALTER TABLE legal_files DROP INDEX legal_files_search_fulltext');
            }
        }

        Schema::table('legal_files', function (Blueprint $table): void {
            $table->string('loan_reference')->nullable()->index()->after('reference_number');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE legal_files ADD FULLTEXT legal_files_search_fulltext '.
                '(reference_number, loan_reference, purchaser, vendor, property)'
            );
        }
    }
};
