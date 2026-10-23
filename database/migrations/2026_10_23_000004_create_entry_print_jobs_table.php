<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entry_print_jobs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legal_file_id')->constrained('legal_files')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('type', 16);
            $table->string('status', 16)->default('pending');
            $table->timestamp('printed_at')->nullable();
            $table->foreignId('printed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'requested_by', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_print_jobs');
    }
};
