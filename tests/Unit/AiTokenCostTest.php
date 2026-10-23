<?php

namespace Tests\Unit;

use App\Support\AiTokenCost;
use Tests\TestCase;

class AiTokenCostTest extends TestCase
{
    public function test_flash_lite_31_cost_in_myr(): void
    {
        config(['lrms.ai_usage_usd_myr' => 4.20]);

        // 1M input @ $0.25 + 1M output @ $1.50 = $1.75 → RM 7.35
        $cost = AiTokenCost::costMyr('gemini-3.1-flash-lite', 1_000_000, 1_000_000);

        $this->assertEqualsWithDelta(7.35, $cost, 0.000001);
        $this->assertSame('RM 7.35', AiTokenCost::formatMyr($cost));
    }

    public function test_output_includes_thoughts_tokens(): void
    {
        $this->assertSame(140, AiTokenCost::outputTokens(100, 40));

        config(['lrms.ai_usage_usd_myr' => 4.20]);

        $cost = AiTokenCost::costMyr('gemini-3.1-flash-lite', 1000, 100, 50);
        $expectedUsd = (1000 * 0.25 / 1_000_000) + (150 * 1.50 / 1_000_000);

        $this->assertEqualsWithDelta($expectedUsd * 4.20, $cost, 0.0000001);
    }

    public function test_cached_tokens_use_cheaper_rate(): void
    {
        config(['lrms.ai_usage_usd_myr' => 4.20]);

        $full = AiTokenCost::costMyr('gemini-3.1-flash-lite', 1000, 0, 0, 0);
        $cached = AiTokenCost::costMyr('gemini-3.1-flash-lite', 1000, 0, 0, 1000);

        $this->assertLessThan($full, $cached);
        $this->assertEqualsWithDelta(1000 * 0.025 / 1_000_000 * 4.20, $cached, 0.0000001);
    }
}
