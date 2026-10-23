<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_token_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('feature', 64);
            $table->string('model', 96);
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('candidates_tokens')->default(0);
            $table->unsignedInteger('thoughts_tokens')->default(0);
            $table->unsignedInteger('cached_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->boolean('success')->default(false);
            $table->string('error', 255)->nullable();
            $table->timestamps();

            $table->index(['created_at']);
            $table->index(['feature', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_token_usages');
    }
};
