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
            $table->date('start_date')->nullable()->after('matter_type');
            $table->date('end_date')->nullable()->after('start_date');
        });

        if (Schema::hasColumn('legal_files', 'important_date')) {
            DB::table('legal_files')
                ->whereNotNull('important_date')
                ->update(['start_date' => DB::raw('important_date')]);

            Schema::table('legal_files', function (Blueprint $table): void {
                $table->dropColumn('important_date');
            });
        }
    }

    public function down(): void
    {
        Schema::table('legal_files', function (Blueprint $table): void {
            $table->date('important_date')->nullable()->after('matter_type');
        });

        DB::table('legal_files')
            ->whereNotNull('start_date')
            ->update(['important_date' => DB::raw('start_date')]);

        Schema::table('legal_files', function (Blueprint $table): void {
            $table->dropColumn(['start_date', 'end_date']);
        });
    }
};
