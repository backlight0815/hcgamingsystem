<?php

namespace App\Services\MarketInsight;

interface InsightExplanationGenerator
{
    public function generate(array $indicators, array $factors, array $risk, float $score, string $signal): string;
}
