<?php

namespace App\Support;

/**
 * Estimate Gemini paid-tier cost, shown in RM.
 *
 * Google bills in USD (per 1M tokens). We convert with LRMS_USD_MYR_RATE.
 * Output includes thinking tokens. Cached input uses the cache rate when present.
 *
 * ponytail: rates/FX drift — update config/lrms.php ai_usage_rates + LRMS_USD_MYR_RATE.
 */
class AiTokenCost
{
    /**
     * @return array{input: float, output: float, cached: float}
     */
    public static function ratesFor(string $model): array
    {
        $models = config('lrms.ai_usage_rates.models', []);
        $rates = $models[$model] ?? config('lrms.ai_usage_rates.default', [
            'input' => 0.25,
            'output' => 1.50,
            'cached' => 0.025,
        ]);

        return [
            'input' => (float) ($rates['input'] ?? 0.25),
            'output' => (float) ($rates['output'] ?? 1.50),
            'cached' => (float) ($rates['cached'] ?? 0.025),
        ];
    }

    public static function usdToMyr(float $usd): float
    {
        return $usd * (float) config('lrms.ai_usage_usd_myr', 4.20);
    }

    public static function inputTokens(int $promptTokens): int
    {
        return max(0, $promptTokens);
    }

    public static function outputTokens(int $candidatesTokens, int $thoughtsTokens = 0): int
    {
        return max(0, $candidatesTokens) + max(0, $thoughtsTokens);
    }

    public static function costUsd(
        string $model,
        int $promptTokens,
        int $candidatesTokens,
        int $thoughtsTokens = 0,
        int $cachedTokens = 0,
    ): float {
        $rates = self::ratesFor($model);
        $cached = min(max(0, $cachedTokens), max(0, $promptTokens));
        $uncachedInput = max(0, $promptTokens) - $cached;
        $output = self::outputTokens($candidatesTokens, $thoughtsTokens);

        return ($uncachedInput * $rates['input'] / 1_000_000)
            + ($cached * $rates['cached'] / 1_000_000)
            + ($output * $rates['output'] / 1_000_000);
    }

    public static function costMyr(
        string $model,
        int $promptTokens,
        int $candidatesTokens,
        int $thoughtsTokens = 0,
        int $cachedTokens = 0,
    ): float {
        return self::usdToMyr(self::costUsd(
            $model,
            $promptTokens,
            $candidatesTokens,
            $thoughtsTokens,
            $cachedTokens,
        ));
    }

    public static function formatMyr(float $amount): string
    {
        if ($amount <= 0) {
            return 'RM 0.00';
        }

        if ($amount >= 0.01) {
            return 'RM '.number_format($amount, 2);
        }

        return 'RM '.number_format($amount, 4);
    }
}
