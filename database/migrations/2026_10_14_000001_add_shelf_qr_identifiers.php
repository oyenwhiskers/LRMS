<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shelves', function (Blueprint $table): void {
            $table->uuid('qr_identifier')->nullable()->unique();
        });

        DB::table('shelves')->select('id')->orderBy('id')->chunkById(100, function ($shelves): void {
            foreach ($shelves as $shelf) {
                DB::table('shelves')->where('id', $shelf->id)->update(['qr_identifier' => (string) Str::uuid()]);
            }
        });

        Schema::table('shelves', function (Blueprint $table): void {
            $table->uuid('qr_identifier')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('shelves', function (Blueprint $table): void {
            $table->dropUnique(['qr_identifier']);
            $table->dropColumn('qr_identifier');
        });
    }
};
