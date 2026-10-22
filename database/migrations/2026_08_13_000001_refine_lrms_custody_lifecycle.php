<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('file_movements', function (Blueprint $table) {
            $table->foreignId('receiver_id')->nullable()->after('employee_id')->constrained('staff')->nullOnDelete();
            $table->index(['type', 'occurred_at']);
        });

        Schema::table('legal_files', function (Blueprint $table) {
            $table->index(['status', 'created_at']);
        });

        DB::table('legal_files')
            ->where('status', 'available')
            ->update(['status' => 'stored']);

        DB::table('legal_files')
            ->where('status', 'borrowed')
            ->update(['status' => 'borrowed']);

        DB::table('legal_files')
            ->where('status', 'missing')
            ->update(['status' => 'missing']);
    }

    public function down(): void
    {
        DB::table('legal_files')
            ->where('status', 'stored')
            ->update(['status' => 'available']);

        Schema::table('legal_files', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
        });

        Schema::table('file_movements', function (Blueprint $table) {
            $table->dropIndex(['type', 'occurred_at']);
            $table->dropConstrainedForeignId('receiver_id');
        });
    }
};
