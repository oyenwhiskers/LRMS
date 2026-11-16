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

class ImportMasterRegistry
{
    /** @var list<string> */
    private const REQUIRED_KEYS = [
        'registry_type',
        'reference_number',
        'purchaser',
        'vendor',
        'property',
        'file_date',
        'start_date',
        'loan_bank_code',
        'spa_reference',
        'loan_reference',
        'associated_references',
        'loan_pic',
        'spa_pic',
    ];

    /**
     * @return array{
     *     dry_run: bool,
     *     total: int,
     *     imported: int,
     *     registry_types: array<string, int>,
     *     categories: list<array{original: string, code: string, created: bool}>,
     *     sequences: array<string, int>,
     *     unmatched_pics: list<string>,
     *     linked: int,
     *     unresolved_links: list<string>,
     *     already_imported: bool,
     *     colliding_refs: list<string>
     * }
     */
    public function handle(string $path, User $user, bool $dryRun = false): array
    {
        $rows = $this->loadRows($path);
        $validated = $this->validateRows($rows);
        $refs = array_column($validated, 'reference_number');

        $existing = LegalFile::query()
            ->whereIn('reference_number', $refs)
            ->pluck('reference_number')
            ->all();

        if ($existing !== []) {
            return [
                'dry_run' => $dryRun,
                'total' => count($validated),
                'imported' => 0,
                'registry_types' => $this->countBy($validated, 'registry_type'),
                'categories' => [],
                'sequences' => [],
                'unmatched_pics' => [],
                'linked' => 0,
                'unresolved_links' => [],
                'already_imported' => count($existing) === count($refs),
                'colliding_refs' => array_values($existing),
            ];
        }

        $staffByName = $this->staffLookup();
        $categoryPlan = $this->planCategories($validated);
        $unmatchedPics = [];
        foreach ($validated as $row) {
            $pic = $row['pic'];
            if ($pic !== null && ! isset($staffByName[$pic])) {
                $unmatchedPics[$pic] = true;
            }
        }

        $linkPreview = $this->previewLinks($validated);
        $sequencePreview = [];
        foreach ($categoryPlan['by_code'] as $code => $meta) {
            $sequencePreview[$code] = ($meta['max_number'] ?? 0) + 1;
        }

        if ($dryRun) {
            $categories = [];
            foreach ($categoryPlan['by_code'] as $code => $meta) {
                $exists = CaseCategory::query()->where('code', $code)->exists();
                $categories[] = [
                    'original' => $meta['name'],
                    'code' => $code,
                    'created' => ! $exists,
                ];
            }

            return [
                'dry_run' => true,
                'total' => count($validated),
                'imported' => 0,
                'registry_types' => $this->countBy($validated, 'registry_type'),
                'categories' => $categories,
                'sequences' => $sequencePreview,
                'unmatched_pics' => array_keys($unmatchedPics),
                'linked' => $linkPreview['linked'],
                'unresolved_links' => $linkPreview['unresolved'],
                'already_imported' => false,
                'colliding_refs' => [],
            ];
        }

        return DB::transaction(function () use ($validated, $user, $staffByName, $categoryPlan, $unmatchedPics) {
            [$categoriesByCode, $createdFlags] = $this->ensureCategories($categoryPlan['by_code']);
            $createdFiles = [];
            $maxByCategoryId = [];

            foreach ($validated as $row) {
                $code = $row['category_code'];
                $category = $categoriesByCode[$code];
                $picId = $row['pic'] !== null ? ($staffByName[$row['pic']] ?? null) : null;
                $fileDate = $row['file_date']
                    ? Carbon::parse($row['file_date'], config('app.timezone'))->startOfDay()
                    : now();

                $file = LegalFile::create([
                    'reference_number' => $row['reference_number'],
                    'case_category_id' => $category->id,
                    'case_type' => $row['case_type'],
                    'case_sub_type' => null,
                    'matter_type' => $category->name,
                    'purchaser' => $row['purchaser'],
                    'vendor' => $row['vendor'],
                    'bank' => $row['bank'],
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

                $createdFiles[] = [
                    'file' => $file,
                    'link_refs' => $row['link_refs'],
                ];

                if ($row['trailing_number'] !== null) {
                    $cid = (int) $category->id;
                    $maxByCategoryId[$cid] = max($maxByCategoryId[$cid] ?? 0, $row['trailing_number']);
                }
            }

            $sequences = [];
            foreach ($categoriesByCode as $code => $category) {
                $max = $maxByCategoryId[(int) $category->id] ?? 0;
                $sequences[$code] = $this->bumpSequence($category, $max);
            }

            $linkResult = $this->applyLinks($createdFiles);

            $categoryReport = [];
            foreach ($categoryPlan['by_code'] as $code => $meta) {
                $categoryReport[] = [
                    'original' => $meta['name'],
                    'code' => $code,
                    'created' => (bool) ($createdFlags[$code] ?? false),
                ];
            }

            return [
                'dry_run' => false,
                'total' => count($validated),
                'imported' => count($validated),
                'registry_types' => $this->countBy($validated, 'registry_type'),
                'categories' => $categoryReport,
                'sequences' => $sequences,
                'unmatched_pics' => array_keys($unmatchedPics),
                'linked' => $linkResult['linked'],
                'unresolved_links' => $linkResult['unresolved'],
                'already_imported' => false,
                'colliding_refs' => [],
            ];
        });
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
     *     registry_type: string,
     *     case_type: string,
     *     category_original: string,
     *     category_code: string,
     *     bank: ?string,
     *     purchaser: string,
     *     vendor: ?string,
     *     property: string,
     *     file_date: ?string,
     *     start_date: ?string,
     *     pic: ?string,
     *     trailing_number: ?int,
     *     link_refs: list<string>
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

            $registryType = strtolower(trim((string) $row['registry_type']));
            if (! in_array($registryType, ['loan', 'project_spa'], true)) {
                throw new InvalidArgumentException("Row [{$index}] invalid registry_type: {$registryType}");
            }

            // Hard rule: preserve printed reference exactly (trim only).
            $ref = trim((string) $row['reference_number']);
            if ($ref === '') {
                throw new InvalidArgumentException("Row [{$index}] reference_number is required.");
            }
            if (isset($seen[$ref])) {
                throw new InvalidArgumentException("Duplicate reference_number in file: {$ref}");
            }
            $seen[$ref] = true;

            $purchaser = trim((string) $row['purchaser']);
            if ($purchaser === '') {
                throw new InvalidArgumentException("Row [{$index}] purchaser is required.");
            }

            $vendor = $this->nullableString($row['vendor']);
            $property = $row['property'] === null ? '' : trim((string) $row['property']);
            $bankCode = $this->nullableString($row['loan_bank_code']);
            if ($bankCode !== null) {
                $bankCode = strtoupper($bankCode);
            }

            $prefix = $this->referencePrefix($ref);
            if ($registryType === 'loan') {
                $categoryOriginal = $bankCode ?? $prefix;
                $caseType = LegalFile::TYPE_LOAN;
                $bank = $bankCode;
                $pic = $this->nullableString($row['loan_pic']);
            } else {
                $categoryOriginal = $prefix;
                $caseType = LegalFile::TYPE_SNP;
                $bank = null;
                $pic = $this->nullableString($row['spa_pic']);
            }

            if ($pic !== null) {
                $pic = mb_strtolower($pic);
            }

            $linkRefs = [];
            foreach (['spa_reference', 'loan_reference'] as $linkKey) {
                $value = $this->nullableString($row[$linkKey] ?? null);
                if ($value !== null) {
                    $linkRefs[] = $value;
                }
            }
            $associated = $row['associated_references'] ?? [];
            if (! is_array($associated)) {
                throw new InvalidArgumentException("Row [{$index}] associated_references must be an array.");
            }
            foreach ($associated as $assoc) {
                $value = $this->nullableString($assoc);
                if ($value !== null) {
                    $linkRefs[] = $value;
                }
            }
            $linkRefs = array_values(array_unique($linkRefs));

            $out[] = [
                'reference_number' => $ref,
                'registry_type' => $registryType,
                'case_type' => $caseType,
                'category_original' => $categoryOriginal,
                'category_code' => '', // filled in planCategories
                'bank' => $bank,
                'purchaser' => $purchaser,
                'vendor' => $vendor,
                'property' => $property,
                'file_date' => $this->normalizeDate($row['file_date'] ?? null, "Row [{$index}] file_date"),
                'start_date' => $this->normalizeDate($row['start_date'] ?? null, "Row [{$index}] start_date"),
                'pic' => $pic,
                'trailing_number' => $this->parseTrailingNumber($ref),
                'link_refs' => $linkRefs,
            ];
        }

        $planned = $this->assignCategoryCodes($out);
        foreach ($planned as $i => $row) {
            $out[$i]['category_code'] = $row['category_code'];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function assignCategoryCodes(array $rows): array
    {
        /** @var array<string, string> original upper => code */
        $map = [];
        /** @var array<string, string> code => first original */
        $codeOwners = [];

        foreach ($rows as $i => $row) {
            $original = (string) $row['category_original'];
            $key = mb_strtoupper($original);
            if (! isset($map[$key])) {
                $code = $this->sanitizeCategoryCode($original);
                $base = $code;
                $suffix = 1;
                while (isset($codeOwners[$code]) && $codeOwners[$code] !== $key) {
                    $trim = max(1, 20 - strlen((string) $suffix));
                    $code = substr($base, 0, $trim).$suffix;
                    $suffix++;
                }
                $codeOwners[$code] = $key;
                $map[$key] = $code;
            }
            $rows[$i]['category_code'] = $map[$key];
        }

        return $rows;
    }

    public function sanitizeCategoryCode(string $original): string
    {
        $upper = strtoupper(trim($original));
        if ($upper === '') {
            return 'UNKNOWN';
        }

        if (preg_match('/^[A-Z0-9]{1,20}$/', $upper)) {
            return $upper;
        }

        $stripped = preg_replace('/[^A-Z0-9]/', '', $upper) ?? '';
        if ($stripped === '') {
            return 'UNKNOWN';
        }

        return substr($stripped, 0, 20);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{by_code: array<string, array{name: string, max_number: int, will_create: bool}>}
     */
    private function planCategories(array $rows): array
    {
        $byCode = [];
        foreach ($rows as $row) {
            $code = $row['category_code'];
            if (! isset($byCode[$code])) {
                $byCode[$code] = [
                    'name' => $row['category_original'],
                    'max_number' => 0,
                    'will_create' => ! CaseCategory::query()->where('code', $code)->exists(),
                ];
            }
            if ($row['trailing_number'] !== null) {
                $byCode[$code]['max_number'] = max($byCode[$code]['max_number'], $row['trailing_number']);
            }
        }

        return ['by_code' => $byCode];
    }

    /**
     * @param  array<string, array{name: string, max_number: int, will_create?: bool}>  $byCode
     * @return array{0: array<string, CaseCategory>, 1: array<string, bool>}
     */
    private function ensureCategories(array $byCode): array
    {
        $out = [];
        $created = [];
        foreach ($byCode as $code => $meta) {
            $category = CaseCategory::query()->where('code', $code)->lockForUpdate()->first();
            $wasCreated = false;
            if (! $category) {
                $category = CaseCategory::create([
                    'code' => $code,
                    'name' => $meta['name'],
                    'is_active' => true,
                ]);
                $wasCreated = true;
            }
            $out[$code] = $category;
            $created[$code] = $wasCreated;
        }

        return [$out, $created];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{linked: int, unresolved: list<string>}
     */
    private function previewLinks(array $rows): array
    {
        $wanted = [];
        foreach ($rows as $row) {
            foreach ($row['link_refs'] as $ref) {
                $wanted[$ref] = true;
            }
        }
        if ($wanted === []) {
            return ['linked' => 0, 'unresolved' => []];
        }

        // Include refs that will be created in this same import.
        $importRefs = [];
        foreach ($rows as $row) {
            $importRefs[$row['reference_number']] = true;
        }

        $existing = LegalFile::query()
            ->whereIn('reference_number', array_keys($wanted))
            ->pluck('reference_number')
            ->all();
        $existingSet = array_fill_keys($existing, true);

        $linked = 0;
        $unresolved = [];
        foreach (array_keys($wanted) as $ref) {
            if (isset($existingSet[$ref]) || isset($importRefs[$ref])) {
                $linked++;
            } else {
                $unresolved[] = $ref;
            }
        }

        return ['linked' => $linked, 'unresolved' => $unresolved];
    }

    /**
     * @param  list<array{file: LegalFile, link_refs: list<string>}>  $createdFiles
     * @return array{linked: int, unresolved: list<string>}
     */
    private function applyLinks(array $createdFiles): array
    {
        $allLinkRefs = [];
        foreach ($createdFiles as $item) {
            foreach ($item['link_refs'] as $ref) {
                $allLinkRefs[$ref] = true;
            }
        }

        $idByRef = LegalFile::query()
            ->whereIn('reference_number', array_keys($allLinkRefs))
            ->pluck('id', 'reference_number')
            ->all();

        $linkedPairs = 0;
        $unresolved = [];

        foreach ($createdFiles as $item) {
            /** @var LegalFile $file */
            $file = $item['file'];
            $relatedIds = [];
            foreach ($item['link_refs'] as $ref) {
                if (isset($idByRef[$ref])) {
                    $relatedIds[] = (int) $idByRef[$ref];
                    $linkedPairs++;
                } else {
                    $unresolved[$ref] = true;
                }
            }
            if ($relatedIds !== []) {
                $file->syncRelatedFiles($relatedIds);
            }
        }

        return [
            'linked' => $linkedPairs,
            'unresolved' => array_keys($unresolved),
        ];
    }

    private function referencePrefix(string $reference): string
    {
        $pos = strpos($reference, '/');
        if ($pos === false) {
            return $reference;
        }

        return substr($reference, 0, $pos);
    }

    private function parseTrailingNumber(string $reference): ?int
    {
        // Prefer /NN at end, allowing optional (TAG) suffix: PBB/2026/02(DGG)
        if (preg_match('#/(\d+)(?:\([^)]*\))?$#', $reference, $m)) {
            return (int) $m[1];
        }
        if (preg_match('#(\d+)$#', $reference, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
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
     * @return array<string, int>
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
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private function countBy(array $rows, string $key): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $value = (string) $row[$key];
            $counts[$value] = ($counts[$value] ?? 0) + 1;
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
