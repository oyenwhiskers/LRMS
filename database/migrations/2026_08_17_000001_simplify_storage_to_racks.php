<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cabinets')) {
            $duplicateCodes = DB::table('cabinets')
                ->select('code')
                ->groupBy('code')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('code');

            foreach ($duplicateCodes as $code) {
                $cabinets = DB::table('cabinets')
                    ->join('rooms', 'cabinets.room_id', '=', 'rooms.id')
                    ->where('cabinets.code', $code)
                    ->select('cabinets.id', 'rooms.code as room_code')
                    ->get();

                foreach ($cabinets as $cabinet) {
                    DB::table('cabinets')
                        ->where('id', $cabinet->id)
                        ->update(['code' => $cabinet->room_code.'-'.$code]);
                }
            }

            Schema::rename('cabinets', 'racks');
        }

        if (! Schema::hasTable('racks')) {
            return;
        }

        if (Schema::hasColumn('racks', 'room_id')) {
            Schema::table('racks', function (Blueprint $table) {
                $table->dropForeign('cabinets_room_id_foreign');
            });

            Schema::table('racks', function (Blueprint $table) {
                $table->dropUnique('cabinets_room_id_code_unique');
                $table->dropColumn('room_id');
            });

            Schema::table('racks', function (Blueprint $table) {
                $table->unique('code');
            });
        }

        if (Schema::hasColumn('shelves', 'cabinet_id')) {
            Schema::table('shelves', function (Blueprint $table) {
                $table->dropForeign('shelves_cabinet_id_foreign');
                $table->dropUnique('shelves_cabinet_id_code_unique');
            });

            Schema::table('shelves', function (Blueprint $table) {
                $table->renameColumn('cabinet_id', 'rack_id');
            });

            Schema::table('shelves', function (Blueprint $table) {
                $table->foreign('rack_id')->references('id')->on('racks')->restrictOnDelete();
                $table->unique(['rack_id', 'code']);
            });
        }

        Schema::dropIfExists('rooms');
    }

    public function down(): void
    {
        if (! Schema::hasTable('racks')) {
            return;
        }

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        $roomId = DB::table('rooms')->insertGetId([
            'code' => 'MAIN',
            'name' => config('lrms.storage.room_name', 'Main File Room'),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (Schema::hasColumn('shelves', 'rack_id')) {
            Schema::table('shelves', function (Blueprint $table) {
                $table->dropForeign(['rack_id']);
                $table->dropUnique(['rack_id', 'code']);
            });

            Schema::table('shelves', function (Blueprint $table) {
                $table->renameColumn('rack_id', 'cabinet_id');
            });
        }

        Schema::rename('racks', 'cabinets');

        Schema::table('cabinets', function (Blueprint $table) {
            $table->foreignId('room_id')->after('id')->default($roomId)->constrained()->restrictOnDelete();
            $table->dropUnique(['code']);
            $table->unique(['room_id', 'code']);
        });

        DB::table('cabinets')->update(['room_id' => $roomId]);

        Schema::table('shelves', function (Blueprint $table) {
            $table->foreign('cabinet_id')->references('id')->on('cabinets')->restrictOnDelete();
            $table->unique(['cabinet_id', 'code']);
        });
    }
};
