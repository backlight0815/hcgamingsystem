<?php

use App\Services\TradingJournalAnalytics;

test('daily profit loss groups trades by the 5am malaysia trading day reset', function () {
    $analytics = new TradingJournalAnalytics();

    $dailyProfitLoss = $analytics->dailyProfitLoss(collect([
        ['close_date' => '2026-06-19 04:30:00', 'profit_loss' => -50],
        ['close_date' => '2026-06-19 05:00:00', 'profit_loss' => 120],
        ['close_date' => '2026-06-19 08:15:00', 'profit_loss' => 30],
    ]));

    expect($dailyProfitLoss->all())->toBe([
        '2026-06-18' => -50.0,
        '2026-06-19' => 150.0,
    ]);
});

test('profitable day rule uses the configured 5am reset boundary', function () {
    $analytics = new TradingJournalAnalytics();

    $rule = $analytics->profitableDayRule(collect([
        ['close_date' => '2026-06-19 04:30:00', 'profit_loss' => 80],
        ['close_date' => '2026-06-19 05:00:00', 'profit_loss' => -20],
        ['close_date' => '2026-06-19 09:00:00', 'profit_loss' => 70],
    ]), 2);

    expect($rule['profitable_dates'])->toBe(['2026-06-18', '2026-06-19'])
        ->and($rule['has_profitable_day'])->toBeTrue()
        ->and($rule['trading_day_reset_time'])->toBe('05:00:00');
});
