<?php

namespace App\Domain\Import;

use App\Models\CaseCategory;
use App\Models\FileMovement;
use App\Models\LegalFile;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ImportSubRegistry
{
    /** @var list<string> */
    private const REQUIRED_KEYS = [
        'source_row',
        'sequence_no',
        'case_category_code',
        'reference_number',
        'case_type',
        'case_sub_type',
        'file_date',
        'start_date',
        'purchaser',
        'vendor',
        'property',
        'spa_pic',
    ];

    /** @var list<string> */
    private const ALLOWED_CASE_TYPES = [
        LegalFile::TYPE_SNP,
        LegalFile::TYPE_MOT,
        LegalFile::TYPE_DOA,
        LegalFile::TYPE_SUBLEASE,
    ];

    /** @var list<string|null> */
    private const ALLOWED_SUB_TYPES = [
        null,
        LegalFile::SUB_TYPE_BY_CASH,
        LegalFile::SUB_TYPE_LOVE_AND_AFFECTION,
        LegalFile::SUB_TYPE_MOT,
        LegalFile::SUB_TYPE_DOA,
    ];

    /**
     * @return array{
     *     dry_run: bool,
     *     total: int,
     *     imported: int,
     *     max_number: int,
     *     next_sequence: int|null,
     *     unmatched_pics: list<string>,
     *     case_types: array<string, int>,
     *     already_imported: bool,
     *     colliding_refs: list<string>
     * }
     */
    public function handle(string $path, User $user, bool $dryRun = false): array
    {
        $rows = $this->loadRows($path);
        $validated = $this->validateRows($rows);
        $refs = array_column($validated, 'reference_number');
        $maxNumber = max(array_column($validated, 'number'));

        $existing = LegalFile::query()
            ->whereIn('reference_number', $refs)
            ->pluck('reference_number')
            ->all();

        if ($existing !== []) {
            $allPresent = count($existing) === count($refs);

            return [
                'dry_run' => $dryRun,
                'total' => count($validated),
                'imported' => 0,
                'max_number' => $maxNumber,
                'next_sequence' => null,
                'unmatched_pics' => [],
                'case_types' => $this->countCaseTypes($validated),
                'already_imported' => $allPresent,
                'colliding_refs' => array_values($existing),
            ];
        }

        $category = CaseCategory::query()
            ->where('code', 'CON')
            ->where('is_active', true)
            ->first();

        if (! $category) {
            throw new RuntimeException('Active case category CON was not found.');
        }

        $staffByName = $this->staffLookup();
        $unmatchedPics = [];
        $caseTypes = $this->countCaseTypes($validated);

        foreach ($validated as $row) {
            $pic = $row['spa_pic'];
            if ($pic !== null && ! isset($staffByName[$pic])) {
                $unmatchedPics[$pic] = true;
            }
        }

        if ($dryRun) {
            return [
                'dry_run' => true,
                'total' => count($validated),
                'imported' => 0,
                'max_number' => $maxNumber,
                'next_sequence' => $maxNumber + 1,
                'unmatched_pics' => array_keys($unmatchedPics),
                'case_types' => $caseTypes,
                'already_imported' => false,
                'colliding_refs' => [],
            ];
        }

        $nextSequence = DB::transaction(function () use ($validated, $user, $category, $staffByName, $maxNumber): int {
            $category = CaseCategory::query()->whereKey($category->id)->lockForUpdate()->firstOrFail();

            foreach ($validated as $row) {
                $picKey = $row['spa_pic'];
                $picId = $picKey !== null ? ($staffByName[$picKey] ?? null) : null;
                $fileDate = $row['file_date']
                    ? Carbon::parse($row['file_date'], config('app.timezone'))->startOfDay()
                    : now();

                $file = LegalFile::create([
                    'reference_number' => $row['reference_number'],
                    'case_category_id' => $category->id,
                    'case_type' => $row['case_type'],
                    'case_sub_type' => $row['case_sub_type'],
                    'matter_type' => $category->name,
                    'purchaser' => $row['purchaser'],
                    'vendor' => $row['vendor'],
                    'property' => $row['property'],
                    'start_date' => $row['start_date'],
                    'person_in_charge_id' => $picId,
                    'status' => LegalFile::STATUS_REGISTERED,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);

                $file->timestamps = false;
                $file->forceFill([
                    'created_at' => $fileDate,
                    'updated_at' => $fileDate,
                ])->save();

                FileMovement::create([
                    'legal_file_id' => $file->id,
                    'type' => 'registered',
                    'processed_by' => $user->id,
                    'previous_status' => null,
                    'new_status' => LegalFile::STATUS_REGISTERED,
                    'occurred_at' => $fileDate,
                ]);
            }

            return $this->bumpSequence($category, $maxNumber);
        });

        return [
            'dry_run' => false,
            'total' => count($validated),
            'imported' => count($validated),
            'max_number' => $maxNumber,
            'next_sequence' => $nextSequence,
            'unmatched_pics' => array_keys($unmatchedPics),
            'case_types' => $caseTypes,
            'already_imported' => false,
            'colliding_refs' => [],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadRows(string $path): array
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException("Import file not found: {$path}");
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Import file is not valid JSON.');
        }

        if ($decoded === []) {
            throw new InvalidArgumentException('Import file is empty.');
        }

        return $decoded;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{
     *     reference_number: string,
     *     number: int,
     *     case_type: string,
     *     case_sub_type: ?string,
     *     file_date: ?string,
     *     start_date: ?string,
     *     purchaser: string,
     *     vendor: ?string,
     *     property: string,
     *     spa_pic: ?string
     * }>
     */
    private function validateRows(array $rows): array
    {
        $seen = [];
        $out = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                throw new InvalidArgumentException("Row [{$index}] is not an object.");
            }

            foreach (self::REQUIRED_KEYS as $key) {
                if (! array_key_exists($key, $row)) {
                    throw new InvalidArgumentException("Row [{$index}] missing key {$key}.");
                }
            }

            $code = strtoupper(trim((string) $row['case_category_code']));
            if ($code !== 'CON') {
                throw new InvalidArgumentException("Row [{$index}] case_category_code must be CON.");
            }

            $ref = trim((string) $row['reference_number']);
            if (! preg_match('#^CON/(\d{4})/(\d{3})$#', $ref, $m)) {
                throw new InvalidArgumentException("Row [{$index}] invalid reference_number: {$ref}");
            }

            if (isset($seen[$ref])) {
                throw new InvalidArgumentException("Duplicate reference_number in file: {$ref}");
            }
            $seen[$ref] = true;

            $caseType = strtolower(trim((string) $row['case_type']));
            if (! in_array($caseType, self::ALLOWED_CASE_TYPES, true)) {
                throw new InvalidArgumentException("Row [{$index}] invalid case_type: {$caseType}");
            }

            $subType = $row['case_sub_type'];
            if ($subType !== null) {
                $subType = strtolower(trim((string) $subType));
                if ($subType === '') {
                    $subType = null;
                }
            }
            if (! in_array($subType, self::ALLOWED_SUB_TYPES, true)) {
                throw new InvalidArgumentException("Row [{$index}] invalid case_sub_type: ".json_encode($row['case_sub_type']));
            }

            if ($subType === LegalFile::SUB_TYPE_BY_CASH && $caseType !== LegalFile::TYPE_SNP) {
                throw new InvalidArgumentException("Row [{$index}] by_cash requires case_type snp.");
            }
            if ($subType === LegalFile::SUB_TYPE_LOVE_AND_AFFECTION && $caseType !== LegalFile::TYPE_MOT) {
                throw new InvalidArgumentException("Row [{$index}] love_and_affection requires case_type mot.");
            }

            $purchaser = trim((string) $row['purchaser']);
            if ($purchaser === '') {
                throw new InvalidArgumentException("Row [{$index}] purchaser is required.");
            }

            $vendor = $row['vendor'];
            $vendor = $vendor === null ? null : trim((string) $vendor);
            if ($vendor === '') {
                $vendor = null;
            }

            $property = $row['property'];
            $property = $property === null ? '' : trim((string) $property);

            $spaPic = $row['spa_pic'];
            $spaPic = $spaPic === null ? null : trim((string) $spaPic);
            if ($spaPic === '') {
                $spaPic = null;
            }
            if ($spaPic !== null) {
                $spaPic = mb_strtolower($spaPic);
            }

            $fileDate = $this->normalizeDate($row['file_date'] ?? null, "Row [{$index}] file_date");
            $startDate = $this->normalizeDate($row['start_date'] ?? null, "Row [{$index}] start_date");

            $out[] = [
                'reference_number' => $ref,
                'number' => (int) $m[2],
                'case_type' => $caseType,
                'case_sub_type' => $subType,
                'file_date' => $fileDate,
                'start_date' => $startDate,
                'purchaser' => $purchaser,
                'vendor' => $vendor,
                'property' => $property,
                'spa_pic' => $spaPic,
            ];
        }

        return $out;
    }

    private function normalizeDate(mixed $value, string $label): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim((string) $value);
        if (! preg_match('#^\d{4}-\d{2}-\d{2}$#', $value)) {
            throw new InvalidArgumentException("{$label} must be YYYY-MM-DD.");
        }

        return $value;
    }

    /**
     * @return array<string, int> lowercase full_name => staff id
     */
    private function staffLookup(): array
    {
        $map = [];
        foreach (Staff::query()->where('is_active', true)->get(['id', 'full_name']) as $staff) {
            $key = mb_strtolower(trim((string) $staff->full_name));
            if ($key !== '' && ! isset($map[$key])) {
                $map[$key] = (int) $staff->id;
            }
        }

        return $map;
    }

    /**
     * @param  list<array{case_type: string}>  $rows
     * @return array<string, int>
     */
    private function countCaseTypes(array $rows): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $type = $row['case_type'];
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }

        return $counts;
    }

    private function bumpSequence(CaseCategory $category, int $maxNumber): int
    {
        $sequenceName = $category->sequenceName();
        $desired = $maxNumber + 1;

        DB::table('system_sequences')->insertOrIgnore([
            'name' => $sequenceName,
            'next_value' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $current = (int) DB::table('system_sequences')->where('name', $sequenceName)->lockForUpdate()->value('next_value');
        $next = max($current, $desired);

        DB::table('system_sequences')->where('name', $sequenceName)->update([
            'next_value' => $next,
            'updated_at' => now(),
        ]);

        return $next;
    }
}
