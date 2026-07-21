<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('label');
            $table->string('module')->index();
            $table->string('action');
            $table->timestamps();
        });

        Schema::create('permission_position', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('position_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'position_id']);
        });

        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->string('staff_number')->unique();
            $table->string('full_name');
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('staff_id')->nullable()->unique()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('requested_position_id')->nullable()->after('staff_id')->constrained('positions')->nullOnDelete();
            $table->string('role')->default('user')->after('password')->index();
            $table->string('status')->default('pending')->after('role')->index();
            $table->timestamp('reviewed_at')->nullable()->after('status');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable()->after('reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropConstrainedForeignId('requested_position_id');
            $table->dropConstrainedForeignId('staff_id');
            $table->dropColumn(['role', 'status', 'reviewed_at', 'rejection_reason']);
        });

        Schema::dropIfExists('staff');
        Schema::dropIfExists('permission_position');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('positions');
    }
};
