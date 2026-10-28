<?php

namespace App\Services;

use App\Models\AiTokenUsage;
use App\Models\CaseCategory;
use App\Models\LegalFile;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class GeminiCaseDraftService
{
    /**
     * @param  list<array{path: string, mime: string}>  $images
     * @return array{draft: array<string, mixed>, error: ?string}
     */
    public function draftFromImages(array $images, ?int $userId = null): array
    {
        $key = (string) config('services.gemini.key');

        if ($key === '') {
            return ['draft' => $this->emptyDraft(), 'error' => 'GEMINI_API_KEY belum ditetapkan.'];
        }

        if ($images === []) {
            return ['draft' => $this->emptyDraft(), 'error' => 'Tiada gambar dokumen.'];
        }

        $categories = CaseCategory::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        $categoryLines = $categories
            ->map(fn (CaseCategory $category): string => "{$category->code} — {$category->name} (id:{$category->id})")
            ->implode("\n");

        $pageCount = count($images);
        $caseTypeCodes = implode(' | ', array_map(
            static fn (string $code): string => '"'.$code.'"',
            LegalFile::caseTypes(),
        ));
        $snpTypeCodes = implode(', ', LegalFile::snpTypes());
        $prompt = <<<PROMPT
You are extracting data from a Malaysian law-firm case document photo for case registration of OLDER physical files.
You may receive 1–{$pageCount} ordered page photos of the same physical file (page 1 first). You MUST read EVERY page and merge into one JSON — do not stop after the cover. Later pages often hold property address, title_number, IC, or party details missing from the cover.
Return ONLY valid JSON with these keys:
{
  "reference_number": string | null,
  "case_type": {$caseTypeCodes},
  "case_category_id": number | null,
  "case_category_code": string | null,
  "purchaser": string,
  "borrowers": [{"name": string, "ic_number": string | null}],
  "vendor": string | null,
  "bank": string | null,
  "property": string | null,
  "title_number": string | null,
  "suite_number": string | null,
  "ic_number": string | null,
  "start_date": "YYYY-MM-DD" | null,
  "end_date": "YYYY-MM-DD" | null
}

Rules:
- reference_number is critical: read the existing file / matter / reference number printed on the document (e.g. THY/MW/ABMB/2024/02, CON/2019/0042, LOA/2018/0007, or other firm formats such as File Ref / Our Ref / Ref at the bottom). Copy it exactly as shown. Do NOT invent a new number. Use null only if none is visible.
- case_type must be one of: {$caseTypeCodes}.
- Prefer matching case_category_id / case_category_code from this list:
{$categoryLines}
- Document title drives case_type (pick the most specific match):
  - Facility Agreement / Facilities Agreement / Loan Agreement / Financing-i / Murabahah / Deed of Assignment securing a bank loan or facility → loan.
  - Court / writ / summons / statement of claim → litigation.
  - Sale & Purchase / Sale and Purchase / SPA / Agreement for Sale → snp.
  - Memorandum of Transfer / MOT → mot.
  - Receipt and Reassignment / Receipt & Reassignment → receipt_reassignment. This is NOT doa and NOT doa_post — use only when the title is Receipt and Reassignment.
  - Deed of Assignment (P.O.S.T) / POST / perfection of strata title assignment → doa_post.
  - Deed of Assignment (absolute / without bank facility) → doa.
  - Deed of Gift / Hibah → dog.
  - Power of Attorney / POA → poa.
  - Sublist agreement → sublist.
  - Copy of Title / Copy Title / CT / title search copy → copy_title.
- Bank financing / loan docs → prefer LOA (Loan Documentation). Do NOT treat bank codes or middle tokens inside a firm ref (e.g. CON in THY/YFT/CON/BRAKYAT/2025/18, or ABMB/MIB/UOB) as the case category.
- Fill only what is clearly visible across the provided pages. Cover pages often lack property and title_number — leave those null unless a later page shows them. Do not invent or guess missing fields.
- property: put the charged / subject property description OR postal / site address when printed (labels such as Property, Address, Alamat, Schedule of Property, Parcel, Lot, HS(D), Geran, PN, description of land). Prefer the property address from later pages over a letterhead office address. Copy the full address block into property when that is what the page shows.
- For loan: fill borrowers in document order — party (1) first (main), then (2), (3), …. Include each customer's NRIC in ic_number when printed. Do not drop co-borrowers. Also set purchaser to all borrower names joined with " & " (e.g. "LOOK KAR YANG & LIM PUI SHEE"). Fill bank and start_date if dated; property/title_number from any page if printed.
- For S&P-family types ({$snpTypeCodes}): fill purchaser, vendor, property, title_number only if printed; borrowers may be empty. Map transferor/assignor/donor → vendor and transferee/assignee/donee → purchaser when those labels appear.
- For litigation: fill suite_number, purchaser (person name), ic_number when visible; leave property empty; borrowers may be empty.
- Use null for unknown fields. Do not invent category codes outside the list.
PROMPT;

        try {
            $parts = [['text' => $prompt]];
            foreach ($images as $index => $image) {
                $bytes = file_get_contents($image['path']);
                if ($bytes === false) {
                    throw new RuntimeException('Unable to read document image.');
                }
                $parts[] = ['text' => 'Page '.($index + 1).' of '.$pageCount.' (read this page for any fields still missing):'];
                $parts[] = [
                    'inlineData' => [
                        'mimeType' => ($image['mime'] ?? '') !== '' ? $image['mime'] : 'image/jpeg',
                        'data' => base64_encode($bytes),
                    ],
                    'mediaResolution' => [
                        'level' => 'media_resolution_high',
                    ],
                ];
            }

            $payload = [
                'contents' => [['parts' => $parts]],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'temperature' => 0.1,
                ],
            ];

            $lastStatus = null;
            $lastBody = null;
            $lastModel = null;
            $busy = false;
            // ponytail: ~30s/page beyond the first; raise if large multi-page drafts time out
            $timeout = min(300, 60 + (30 * max(0, count($images) - 1)));

            foreach ($this->candidateModels() as $model) {
                $url = sprintf(
                    'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
                    urlencode($model),
                    urlencode($key),
                );

                $response = Http::timeout($timeout)->acceptJson()->post($url, $payload);
                $lastStatus = $response->status();
                $lastBody = $response->body();
                $lastModel = $model;
                $json = $response->json();
                $json = is_array($json) ? $json : null;

                if (in_array($lastStatus, [404, 429, 503], true)) {
                    $busy = $busy || in_array($lastStatus, [429, 503], true);
                    $this->recordUsage($json, $model, $userId, $lastStatus, false, 'retryable_'.$lastStatus);
                    Log::warning('Gemini draft retryable failure', [
                        'status' => $lastStatus,
                        'body' => $lastBody,
                        'model' => $model,
                    ]);

                    continue;
                }

                if (! $response->successful()) {
                    $this->recordUsage($json, $model, $userId, $lastStatus, false, 'http_'.$lastStatus);
                    Log::warning('Gemini draft failed', [
                        'status' => $lastStatus,
                        'body' => $lastBody,
                        'model' => $model,
                    ]);

                    return ['draft' => $this->emptyDraft(), 'error' => 'AI tidak dapat baca dokumen. Sila isi borang sendiri.'];
                }

                $text = $this->responseText($json);
                if ($text === null) {
                    $this->recordUsage($json, $model, $userId, $lastStatus, false, 'empty_response');

                    return ['draft' => $this->emptyDraft(), 'error' => 'AI tidak memberi jawapan. Sila isi borang sendiri.'];
                }

                $decoded = json_decode($this->extractJson($text), true);
                if (! is_array($decoded)) {
                    $this->recordUsage($json, $model, $userId, $lastStatus, false, 'invalid_json');

                    return ['draft' => $this->emptyDraft(), 'error' => 'Jawapan AI tidak sah. Sila isi borang sendiri.'];
                }

                $draft = $this->normalizeDraft($decoded, $categories);
                $this->recordUsage($json, $model, $userId, $lastStatus, true);
                Log::info('Gemini draft extracted', [
                    'pages' => $pageCount,
                    'model' => $model,
                    'reference_number' => $draft['reference_number'],
                    'case_type' => $draft['case_type'],
                    'property' => $draft['property'],
                    'title_number' => $draft['title_number'],
                    'purchaser' => $draft['purchaser'] !== '' ? $draft['purchaser'] : null,
                ]);

                return ['draft' => $draft, 'error' => null];
            }

            Log::warning('Gemini draft failed after retries', [
                'status' => $lastStatus,
                'body' => $lastBody,
                'model' => $lastModel,
            ]);

            return [
                'draft' => $this->emptyDraft(),
                'error' => $busy
                    ? 'AI sedang sibuk. Sila cuba semula, atau isi borang sendiri.'
                    : 'AI tidak dapat baca dokumen. Sila isi borang sendiri.',
            ];
        } catch (ConnectionException $e) {
            Log::warning('Gemini connection failed', ['message' => $e->getMessage()]);

            return ['draft' => $this->emptyDraft(), 'error' => 'Tidak dapat hubungi AI. Sila isi borang sendiri.'];
        } catch (Throwable $e) {
            Log::warning('Gemini draft exception', ['message' => $e->getMessage()]);

            return ['draft' => $this->emptyDraft(), 'error' => 'Bacaan dokumen gagal. Sila isi borang sendiri.'];
        }
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function recordUsage(
        ?array $json,
        string $model,
        ?int $userId,
        ?int $httpStatus,
        bool $success,
        ?string $error = null,
    ): void {
        try {
            AiTokenUsage::recordFromGeminiResponse(
                $json,
                AiTokenUsage::FEATURE_ENTRY_CASE_DRAFT,
                $model,
                $userId,
                $httpStatus,
                $success,
                $error,
            );
        } catch (Throwable $e) {
            Log::warning('AI token usage record failed', ['message' => $e->getMessage()]);
        }
    }

    /**
     * @return list<string>
     */
    private function candidateModels(): array
    {
        $primary = (string) config('services.gemini.model', 'gemini-3.1-flash-lite');

        return array_values(array_unique(array_filter([
            $primary,
            'gemini-3.1-flash-lite',
            'gemini-3.5-flash-lite',
            'gemini-3.6-flash',
            'gemini-3.8-flash',
        ])));
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function responseText(?array $json): ?string
    {
        $parts = data_get($json, 'candidates.0.content.parts');
        if (! is_array($parts)) {
            return null;
        }

        foreach ($parts as $part) {
            $text = is_array($part) ? ($part['text'] ?? null) : null;
            if (is_string($text) && trim($text) !== '') {
                return $text;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDraft(): array
    {
        return [
            'reference_number' => null,
            'case_type' => LegalFile::TYPE_SNP,
            'case_category_id' => null,
            'case_category_code' => null,
            'purchaser' => '',
            'borrowers' => [],
            'vendor' => null,
            'bank' => null,
            'property' => null,
            'title_number' => null,
            'suite_number' => null,
            'ic_number' => null,
            'start_date' => null,
            'end_date' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @param  \Illuminate\Support\Collection<int, CaseCategory>  $categories
     * @return array<string, mixed>
     */
    private function normalizeDraft(array $decoded, $categories): array
    {
        $draft = $this->emptyDraft();
        $draft['case_type'] = $this->normalizeCaseType((string) ($decoded['case_type'] ?? LegalFile::TYPE_SNP));

        foreach (['reference_number', 'purchaser', 'vendor', 'bank', 'property', 'title_number', 'suite_number', 'ic_number', 'start_date', 'end_date'] as $field) {
            $value = $decoded[$field] ?? null;
            $draft[$field] = is_string($value) && trim($value) !== '' ? trim($value) : null;
        }
        if (is_string($draft['reference_number'])) {
            $draft['reference_number'] = preg_replace('/\s+/', '', $draft['reference_number']) ?: null;
        }

        $draft['borrowers'] = $this->normalizeBorrowers($decoded['borrowers'] ?? null, (string) ($draft['purchaser'] ?? ''));
        $draft['purchaser'] = $draft['borrowers'] !== []
            ? LegalFile::joinPartyNames(array_column($draft['borrowers'], 'name'))
            : (string) ($draft['purchaser'] ?? '');

        $categoryId = $decoded['case_category_id'] ?? null;
        $categoryCode = isset($decoded['case_category_code']) ? strtoupper(trim((string) $decoded['case_category_code'])) : null;

        $match = null;
        if ($categoryId !== null && $categoryId !== '') {
            $match = $categories->firstWhere('id', (int) $categoryId);
        }
        if (! $match && $categoryCode) {
            $match = $categories->first(fn (CaseCategory $category): bool => strtoupper($category->code) === $categoryCode);
        }

        $draft['case_category_id'] = $match?->id;
        $draft['case_category_code'] = $match?->code;

        return $draft;
    }

    private function normalizeCaseType(string $raw): string
    {
        $type = strtolower(trim($raw));
        if (in_array($type, LegalFile::caseTypes(), true)) {
            return $type;
        }

        foreach (LegalFile::caseTypes() as $code) {
            $label = strtolower(LegalFile::caseTypeLabelFor($code));
            if ($type === $label || $type === str_replace(' and ', ' & ', $label)) {
                return $code;
            }
        }

        return LegalFile::TYPE_SNP;
    }

    /**
     * @return list<array{name: string, ic_number: ?string}>
     */
    private function normalizeBorrowers(mixed $raw, string $fallbackPurchaser): array
    {
        $rows = [];
        if (is_array($raw)) {
            foreach ($raw as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $name = trim((string) ($item['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $ic = trim((string) ($item['ic_number'] ?? ''));
                $rows[] = [
                    'name' => $name,
                    'ic_number' => $ic !== '' ? $ic : null,
                ];
            }
        }

        if ($rows === [] && $fallbackPurchaser !== '') {
            foreach (preg_split('/\s*&\s*|\s*;\s*|\s*,\s*and\s+/i', $fallbackPurchaser) ?: [] as $part) {
                $name = trim((string) $part);
                if ($name !== '') {
                    $rows[] = ['name' => $name, 'ic_number' => null];
                }
            }
        }

        return $rows;
    }

    private function extractJson(string $text): string
    {
        $trimmed = trim($text);
        if (str_starts_with($trimmed, '```')) {
            $trimmed = preg_replace('/^```(?:json)?\s*/i', '', $trimmed) ?? $trimmed;
            $trimmed = preg_replace('/\s*```$/', '', $trimmed) ?? $trimmed;
        }

        return $trimmed;
    }
}
