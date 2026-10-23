<?php

namespace App\Domain\Entry;

use App\Models\CaseCategory;
use App\Models\EntryScan;
use App\Models\FileMovement;
use App\Models\LegalFile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterEntryCase
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(EntryScan $scan, User $user, array $data): LegalFile
    {
        return DB::transaction(function () use ($scan, $user, $data): LegalFile {
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

            if (LegalFile::query()->where('reference_number', $reference)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages([
                    'reference_number' => 'No. rujukan ini sudah wujud dalam sistem.',
                ]);
            }

            // Older physical files keep their printed reference — do not auto-generate CODE/YEAR/….
            $data['reference_number'] = $reference;
            $data['matter_type'] = $category->name;
            $data['created_by'] = $data['updated_by'] = $user->id;
            $data['person_in_charge_id'] = null;
            $borrowers = is_array($data['borrowers'] ?? null) ? $data['borrowers'] : [];
            unset($data['related_file_ids'], $data['borrowers']);

            $file = LegalFile::create($data);

            if ($file->case_type === LegalFile::TYPE_LOAN && $borrowers !== []) {
                $file->syncBorrowers($borrowers);
            }

            FileMovement::create([
                'legal_file_id' => $file->id,
                'type' => 'registered',
                'processed_by' => $user->id,
                'previous_status' => null,
                'new_status' => LegalFile::STATUS_REGISTERED,
                'occurred_at' => $file->created_at,
            ]);

            $scan->update([
                'legal_file_id' => $file->id,
                'status' => EntryScan::STATUS_SAVED,
            ]);

            return $file;
        });
    }
}
