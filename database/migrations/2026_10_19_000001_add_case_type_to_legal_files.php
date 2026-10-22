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
            $table->string('case_type', 20)->default('snp')->after('case_category_id')->index();
            $table->string('bank')->nullable()->after('vendor');
            $table->string('title_number')->nullable()->after('property');
            $table->string('suite_number')->nullable()->after('title_number');
            $table->string('ic_number')->nullable()->after('suite_number');
        });

        // Existing rows were registered on the S&P-shaped form.
        DB::table('legal_files')->update(['case_type' => 'snp']);
    }

    public function down(): void
    {
        Schema::table('legal_files', function (Blueprint $table): void {
            $table->dropIndex(['case_type']);
            $table->dropColumn(['case_type', 'bank', 'title_number', 'suite_number', 'ic_number']);
        });
    }
};
