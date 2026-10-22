<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('legal_files', function (Blueprint $table) {
            $table->foreignId('case_category_id')->nullable()->after('property')->constrained()->restrictOnDelete();
        });

        Schema::create('legal_file_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_file_id')->constrained('legal_files')->restrictOnDelete();
            $table->foreignId('related_legal_file_id')->constrained('legal_files')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['legal_file_id', 'related_legal_file_id']);
        });

        $defaults = [
            ['code' => 'CON', 'name' => 'Conveyancing'],
            ['code' => 'LOA', 'name' => 'Loan Documentation'],
            ['code' => 'TEN', 'name' => 'Tenancy'],
            ['code' => 'COM', 'name' => 'Commercial'],
            ['code' => 'EST', 'name' => 'Estate / Probate'],
            ['code' => 'LIT', 'name' => 'Litigation'],
            ['code' => 'WIL', 'name' => 'Will'],
        ];

        $now = now();
        foreach ($defaults as $category) {
            DB::table('case_categories')->insert([
                'name' => $category['name'],
                'code' => $category['code'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $categories = DB::table('case_categories')->get()->keyBy('code');

        $files = DB::table('legal_files')->select('id', 'reference_number', 'matter_type')->get();
        foreach ($files as $file) {
            $code = null;
            if (preg_match('/^([A-Za-z]+)\/\d{4}\/\d+$/', (string) $file->reference_number, $matches)) {
                $code = strtoupper($matches[1]);
            }

            $categoryId = $code && isset($categories[$code]) ? $categories[$code]->id : null;

            if (! $categoryId && $file->matter_type) {
                $matter = strtolower((string) $file->matter_type);
                $categoryId = match (true) {
                    str_contains($matter, 'loan') => $categories['LOA']->id ?? null,
                    str_contains($matter, 'tenancy') => $categories['TEN']->id ?? null,
                    str_contains($matter, 'commercial') || str_contains($matter, 'lease') => $categories['COM']->id ?? null,
                    str_contains($matter, 'probate') || str_contains($matter, 'estate') || str_contains($matter, 'transmission') => $categories['EST']->id ?? null,
                    str_contains($matter, 'litigation') => $categories['LIT']->id ?? null,
                    str_contains($matter, 'will') => $categories['WIL']->id ?? null,
                    default => $categories['CON']->id ?? null,
                };
            }

            if ($categoryId) {
                DB::table('legal_files')->where('id', $file->id)->update(['case_category_id' => $categoryId]);
            }
        }

        foreach ($categories as $category) {
            $maxNumber = 0;
            $refs = DB::table('legal_files')
                ->where('case_category_id', $category->id)
                ->pluck('reference_number');

            foreach ($refs as $reference) {
                if (preg_match('/\/(\d+)$/', (string) $reference, $matches)) {
                    $maxNumber = max($maxNumber, (int) $matches[1]);
                }
            }

            DB::table('system_sequences')->insertOrIgnore([
                'name' => 'case_category_'.$category->id,
                'next_value' => $maxNumber + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_file_links');

        Schema::table('legal_files', function (Blueprint $table) {
            $table->dropConstrainedForeignId('case_category_id');
        });

        $categoryIds = DB::table('case_categories')->pluck('id');
        foreach ($categoryIds as $id) {
            DB::table('system_sequences')->where('name', 'case_category_'.$id)->delete();
        }

        Schema::dropIfExists('case_categories');
    }
};
