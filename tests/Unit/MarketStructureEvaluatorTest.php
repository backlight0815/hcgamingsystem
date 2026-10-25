<?php

namespace Tests\Unit;

use App\Services\MarketStructureEvaluator;
use PHPUnit\Framework\TestCase;

class MarketStructureEvaluatorTest extends TestCase
{
    public function test_m15_choch_does_not_reverse_aligned_h4_and_h1(): void
    {
        $result = (new MarketStructureEvaluator)->evaluate(['h4' => 'bullish', 'h1' => 'bullish', 'm15' => 'bearish_choch']);
        $this->assertSame('Wait for confirmation', $result['classification']);
        $this->assertSame('bullish', $result['major_bias']);
        $this->assertSame('bullish', $result['internal_bias']);
    }

    public function test_h1_invalidation_requires_reassessment(): void
    {
        $result = (new MarketStructureEvaluator)->evaluate(['h4' => 'bullish', 'h1' => 'bullish',
            'm15' => 'bearish_choch', 'h1_invalidated' => true]);
        $this->assertSame('reassessment required', $result['internal_bias']);
    }

    public function test_missing_h4_and_range_middle_are_not_trade_setups(): void
    {
        $evaluator = new MarketStructureEvaluator;
        $this->assertSame('Insufficient data', $evaluator->evaluate(['h1' => 'bullish'])['classification']);
        $this->assertSame('No Trade', $evaluator->evaluate(['h4' => 'bullish', 'h1' => 'bullish',
            'range_middle' => true])['classification']);
    }
}
