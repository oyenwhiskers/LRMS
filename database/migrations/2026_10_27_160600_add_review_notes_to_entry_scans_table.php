<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entry_scans', function (Blueprint $table): void {
            $table->text('review_notes')->nullable()->after('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('entry_scans', function (Blueprint $table): void {
            $table->dropColumn('review_notes');
        });
    }
};
