<?php

namespace App\Services\MarketInsight;

interface MarketDataProvider
{
    public function load(int $datasetId): array;
}
