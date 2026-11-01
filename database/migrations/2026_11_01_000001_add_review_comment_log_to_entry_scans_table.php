<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entry_scans', function (Blueprint $table): void {
            $table->json('review_comment_log')->nullable()->after('review_notes');
        });
    }

    public function down(): void
    {
        Schema::table('entry_scans', function (Blueprint $table): void {
            $table->dropColumn('review_comment_log');
        });
    }
};
