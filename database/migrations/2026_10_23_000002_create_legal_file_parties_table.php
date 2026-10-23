<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_file_parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_file_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('ic_number', 50)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['legal_file_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_file_parties');
    }
};
