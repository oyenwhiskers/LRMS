<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_files', function (Blueprint $table): void {
            $table->string('case_type', 40)->default('snp')->change();
        });
    }

    public function down(): void
    {
        Schema::table('legal_files', function (Blueprint $table): void {
            $table->string('case_type', 20)->default('snp')->change();
        });
    }
};
