<?php

use App\Services\TradingJournalTimeService;

test('same-date earlier close time is treated as overnight next-day close', function () {
    $service = new TradingJournalTimeService();

    $timeline = $service->toMalaysiaTimeline(
        '2026-05-26T11:25',
        '2026-05-26T00:14',
        TradingJournalTimeService::TIMEZONE_MT5,
        180
    );

    expect($timeline['is_valid'])->toBeTrue()
        ->and($timeline['close_rolled_forward'])->toBeTrue()
        ->and($timeline['open_date'])->toBe('2026-05-26 16:25:00')
        ->and($timeline['close_date'])->toBe('2026-05-27 05:14:00');
});

test('same-date later close time stays on the same day', function () {
    $service = new TradingJournalTimeService();

    $timeline = $service->toMalaysiaTimeline(
        '2026-05-26T11:25',
        '2026-05-26T12:14',
        TradingJournalTimeService::TIMEZONE_MT5,
        180
    );

    expect($timeline['is_valid'])->toBeTrue()
        ->and($timeline['close_rolled_forward'])->toBeFalse()
        ->and($timeline['open_date'])->toBe('2026-05-26 16:25:00')
        ->and($timeline['close_date'])->toBe('2026-05-26 17:14:00');
});

test('previous-date close remains invalid instead of being silently shifted', function () {
    $service = new TradingJournalTimeService();

    $timeline = $service->toMalaysiaTimeline(
        '2026-05-26T11:25',
        '2026-05-25T23:14',
        TradingJournalTimeService::TIMEZONE_MT5,
        180
    );

    expect($timeline['is_valid'])->toBeFalse()
        ->and($timeline['close_rolled_forward'])->toBeFalse();
});

test('mt5 utc plus three morning time converts to malaysia time', function () {
    $service = new TradingJournalTimeService();

    expect($service->toMalaysiaDatabase(
        '2026-06-18 10:00:00',
        TradingJournalTimeService::TIMEZONE_MT5,
        180
    ))->toBe('2026-06-18 15:00:00');
});

test('mt5 utc plus three late night time converts to next malaysia day', function () {
    $service = new TradingJournalTimeService();

    expect($service->toMalaysiaDatabase(
        '2026-06-18 23:30:00',
        TradingJournalTimeService::TIMEZONE_MT5,
        180
    ))->toBe('2026-06-19 04:30:00');
});
