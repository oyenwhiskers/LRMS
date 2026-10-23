<?php

namespace App\Domain\Entry;

use App\Models\CaseCategory;
use App\Models\EntryScan;
use App\Models\LegalFile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateEntryCase
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(EntryScan $scan, User $user, array $data): LegalFile
    {
        return DB::transaction(function () use ($scan, $user, $data): LegalFile {
            $file = LegalFile::query()->whereKey($scan->legal_file_id)->lockForUpdate()->firstOrFail();

            $category = CaseCategory::query()
                ->whereKey($data['case_category_id'])
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();

            $reference = trim((string) ($data['reference_number'] ?? ''));
            if ($reference === '') {
                throw ValidationException::withMessages([
                    'reference_number' => 'No. rujukan fail diperlukan (dari dokumen lama).',
                ]);
            }

            $duplicate = LegalFile::query()
                ->where('reference_number', $reference)
                ->whereKeyNot($file->id)
                ->lockForUpdate()
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'reference_number' => 'No. rujukan ini sudah wujud dalam sistem.',
                ]);
            }

            $borrowers = is_array($data['borrowers'] ?? null) ? $data['borrowers'] : [];
            unset($data['related_file_ids'], $data['borrowers']);

            $file->update($data + [
                'reference_number' => $reference,
                'matter_type' => $category->name,
                'updated_by' => $user->id,
            ]);

            if ($file->case_type === LegalFile::TYPE_LOAN) {
                $file->syncBorrowers($borrowers);
            } else {
                $file->parties()->delete();
            }

            return $file->fresh(['parties']);
        });
    }
}
