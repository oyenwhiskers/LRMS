<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_files', function (Blueprint $table): void {
            $table->string('case_sub_type')->nullable()->after('case_type');
        });
    }

    public function down(): void
    {
        Schema::table('legal_files', function (Blueprint $table): void {
            $table->dropColumn('case_sub_type');
        });
    }
};
