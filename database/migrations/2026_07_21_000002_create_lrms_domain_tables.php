<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->string('email')->nullable()->after('full_name');
            $table->string('phone', 30)->nullable()->after('email');
            $table->uuid('qr_identifier')->unique()->nullable()->after('phone');
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('cabinets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->string('code');
            $table->string('name');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['room_id', 'code']);
        });

        Schema::create('shelves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabinet_id')->constrained()->restrictOnDelete();
            $table->string('code');
            $table->string('name');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['cabinet_id', 'code']);
        });

        Schema::create('system_sequences', function (Blueprint $table) {
            $table->string('name')->primary();
            $table->unsignedBigInteger('next_value')->default(1);
            $table->timestamps();
        });

        Schema::create('legal_files', function (Blueprint $table) {
            $table->id();
            $table->string('file_identifier')->unique();
            $table->uuid('qr_identifier')->unique();
            $table->string('reference_number')->unique();
            $table->string('loan_reference')->nullable()->index();
            $table->string('purchaser')->index();
            $table->string('vendor')->nullable()->index();
            $table->text('property');
            $table->string('matter_type')->nullable()->index();
            $table->date('important_date')->nullable();
            $table->foreignId('person_in_charge_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('shelf_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('current_holder_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('status')->default('available')->index();
            $table->timestamp('archived_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['purchaser', 'vendor']);
        });

        Schema::create('movement_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type')->index();
            $table->foreignId('employee_id')->constrained('staff')->restrictOnDelete();
            $table->foreignId('processed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('processed_at')->index();
            $table->timestamps();
        });

        Schema::create('file_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_id')->nullable();
            $table->foreign('batch_id')->references('id')->on('movement_batches')->nullOnDelete();
            $table->foreignId('legal_file_id')->constrained()->restrictOnDelete();
            $table->string('type')->index();
            $table->foreignId('employee_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('previous_holder_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('processed_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('shelf_id')->nullable()->constrained()->nullOnDelete();
            $table->string('previous_status')->nullable();
            $table->string('new_status');
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
            $table->index(['legal_file_id', 'occurred_at']);
        });

        Schema::create('import_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('identifier')->unique();
            $table->string('original_filename');
            $table->string('status')->index();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->json('errors')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_runs');
        Schema::dropIfExists('file_movements');
        Schema::dropIfExists('movement_batches');
        Schema::dropIfExists('legal_files');
        Schema::dropIfExists('system_sequences');
        Schema::dropIfExists('shelves');
        Schema::dropIfExists('cabinets');
        Schema::dropIfExists('rooms');

        Schema::table('staff', function (Blueprint $table) {
            $table->dropUnique(['qr_identifier']);
            $table->dropColumn(['email', 'phone', 'qr_identifier']);
        });
    }
};
