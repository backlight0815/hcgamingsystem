<?php

namespace App\Services\MarketInsight;

interface RiskContextEvaluator
{
    /** @return array{risk:string,volatility:string,daily_range_status:string,warnings:array} */
    public function evaluate(array $indicators, array $factors): array;
}
