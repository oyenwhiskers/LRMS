<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entry_scans', function (Blueprint $table): void {
            $table->string('review_status')->default('pending')->after('status');
            $table->foreignId('reviewed_by')->nullable()->after('review_status')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');

            $table->index(['user_id', 'review_status']);
            $table->index('review_status');
        });
    }

    public function down(): void
    {
        Schema::table('entry_scans', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'review_status']);
            $table->dropIndex(['review_status']);
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['review_status', 'reviewed_at']);
        });
    }
};
