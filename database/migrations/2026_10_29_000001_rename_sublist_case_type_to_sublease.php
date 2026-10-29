<?php

use App\Models\LegalFile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('legal_files')
            ->where('case_type', LegalFile::TYPE_SUBLIST)
            ->update(['case_type' => LegalFile::TYPE_SUBLEASE, 'updated_at' => now()]);

        // Draft AI suggestions may still carry the misspelled code.
        $scans = DB::table('entry_scans')
            ->where('ai_suggestion', 'like', '%sublist%')
            ->select('id', 'ai_suggestion')
            ->get();

        foreach ($scans as $scan) {
            $suggestion = json_decode((string) $scan->ai_suggestion, true);
            if (! is_array($suggestion) || ! isset($suggestion['case_type'])) {
                continue;
            }

            $normalized = LegalFile::normalizeCaseTypeCode((string) $suggestion['case_type']);
            if ($normalized === (string) $suggestion['case_type']) {
                continue;
            }

            $suggestion['case_type'] = $normalized;
            DB::table('entry_scans')->where('id', $scan->id)->update([
                'ai_suggestion' => json_encode($suggestion),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('legal_files')
            ->where('case_type', LegalFile::TYPE_SUBLEASE)
            ->update(['case_type' => LegalFile::TYPE_SUBLIST, 'updated_at' => now()]);
    }
};
