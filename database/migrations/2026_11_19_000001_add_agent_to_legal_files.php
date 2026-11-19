<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('legal_files', 'agent')) {
            Schema::table('legal_files', function (Blueprint $table): void {
                $table->string('agent')->nullable()->index()->after('vendor');
            });
        }

        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $indexExists = collect(DB::select("SHOW INDEX FROM legal_files WHERE Key_name = 'legal_files_search_fulltext'"))->isNotEmpty();
        if ($indexExists) {
            DB::statement('ALTER TABLE legal_files DROP INDEX legal_files_search_fulltext');
        }

        DB::statement(
            'ALTER TABLE legal_files ADD FULLTEXT legal_files_search_fulltext '.
            '(reference_number, purchaser, vendor, agent, property)'
        );
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            $indexExists = collect(DB::select("SHOW INDEX FROM legal_files WHERE Key_name = 'legal_files_search_fulltext'"))->isNotEmpty();
            if ($indexExists) {
                DB::statement('ALTER TABLE legal_files DROP INDEX legal_files_search_fulltext');
            }
        }

        if (Schema::hasColumn('legal_files', 'agent')) {
            Schema::table('legal_files', function (Blueprint $table): void {
                $table->dropIndex(['agent']);
                $table->dropColumn('agent');
            });
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE legal_files ADD FULLTEXT legal_files_search_fulltext '.
                '(reference_number, purchaser, vendor, property)'
            );
        }
    }
};
