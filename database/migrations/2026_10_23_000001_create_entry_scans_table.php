<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entry_scans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('document_path');
            $table->json('ai_suggestion')->nullable();
            $table->foreignId('legal_file_id')->nullable()->constrained('legal_files')->nullOnDelete();
            $table->foreignId('shelf_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('draft')->index();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_scans');
    }
};
