<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiTokenUsage extends Model
{
    public const FEATURE_ENTRY_CASE_DRAFT = 'entry_case_draft';

    protected $fillable = [
        'user_id',
        'feature',
        'model',
        'prompt_tokens',
        'candidates_tokens',
        'thoughts_tokens',
        'cached_tokens',
        'total_tokens',
        'http_status',
        'success',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'prompt_tokens' => 'integer',
            'candidates_tokens' => 'integer',
            'thoughts_tokens' => 'integer',
            'cached_tokens' => 'integer',
            'total_tokens' => 'integer',
            'http_status' => 'integer',
            'success' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Persist Gemini usageMetadata when present. Returns null if the API body has no token fields.
     *
     * @param  array<string, mixed>|null  $json
     */
    public static function recordFromGeminiResponse(
        ?array $json,
        string $feature,
        string $model,
        ?int $userId,
        ?int $httpStatus,
        bool $success,
        ?string $error = null,
    ): ?self {
        $meta = is_array($json['usageMetadata'] ?? null) ? $json['usageMetadata'] : null;
        if ($meta === null) {
            return null;
        }

        $prompt = (int) ($meta['promptTokenCount'] ?? 0);
        $candidates = (int) ($meta['candidatesTokenCount'] ?? 0);
        $thoughts = (int) ($meta['thoughtsTokenCount'] ?? 0);
        $cached = (int) ($meta['cachedContentTokenCount'] ?? 0);
        $total = (int) ($meta['totalTokenCount'] ?? 0);

        // Prefer API total; if absent, sum billed parts (prompt + candidates + thoughts).
        if ($total === 0 && ($prompt + $candidates + $thoughts) > 0) {
            $total = $prompt + $candidates + $thoughts;
        }

        if ($prompt === 0 && $candidates === 0 && $thoughts === 0 && $cached === 0 && $total === 0) {
            return null;
        }

        return self::query()->create([
            'user_id' => $userId,
            'feature' => $feature,
            'model' => $model,
            'prompt_tokens' => $prompt,
            'candidates_tokens' => $candidates,
            'thoughts_tokens' => $thoughts,
            'cached_tokens' => $cached,
            'total_tokens' => $total,
            'http_status' => $httpStatus,
            'success' => $success,
            'error' => $error !== null ? mb_substr($error, 0, 255) : null,
        ]);
    }
}
