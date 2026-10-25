<?php

namespace App\Services\MarketInsight;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class MarketInsightSettings
{
    public const TIMEFRAMES = ['M15' => 15, 'M30' => 30, 'H1' => 60, 'H4' => 240, 'D1' => 1440];

    public const SCENARIOS = ['rising', 'falling', 'sideways', 'volatile'];

    public const DIRECTIONAL = ['bollinger', 'ema', 'macd', 'rsi'];

    public static function defaults(): array
    {
        return [
            'enabled_for_users' => true,
            'indicators' => [
                'bollinger' => ['enabled' => true, 'period' => 20, 'multiplier' => 2.0, 'weight' => 20],
                'ema' => ['enabled' => true, 'fast' => 9, 'slow' => 21, 'weight' => 30],
                'macd' => ['enabled' => true, 'fast' => 12, 'slow' => 26, 'signal' => 9, 'weight' => 30],
                'rsi' => ['enabled' => true, 'period' => 14, 'oversold' => 30, 'overbought' => 70, 'weight' => 20],
                'atr' => ['enabled' => true, 'period' => 14, 'baseline_period' => 20, 'low_ratio' => 0.7, 'high_ratio' => 1.5, 'weight' => 0],
                'adr' => ['enabled' => true, 'period' => 14, 'low_ratio' => 0.7, 'extended_ratio' => 1.25, 'weight' => 0],
            ],
            'thresholds' => ['strong_sell' => -70, 'sell' => -25, 'buy' => 25, 'strong_buy' => 70],
        ];
    }

    public static function current(): array
    {
        $row = DB::table('market_insight_configurations')->orderByDesc('version')->first();

        return ['version' => (int) ($row?->version ?? 1),
            'settings' => $row ? json_decode($row->settings, true, 512, JSON_THROW_ON_ERROR) : self::defaults()];
    }

    public static function validate(array $settings): array
    {
        $rules = ['enabled_for_users' => ['required', 'boolean'], 'indicators' => ['required', 'array'], 'thresholds' => ['required', 'array']];
        foreach (array_keys(self::defaults()['indicators']) as $key) {
            $rules["indicators.$key"] = ['required', 'array'];
            $rules["indicators.$key.enabled"] = ['required', 'boolean'];
            $rules["indicators.$key.weight"] = ['required', 'numeric', 'min:0', 'max:100'];
        }
        foreach (['bollinger.period', 'ema.fast', 'ema.slow', 'macd.fast', 'macd.slow', 'macd.signal', 'rsi.period', 'atr.period', 'atr.baseline_period', 'adr.period'] as $path) {
            $rules['indicators.'.$path] = ['required', 'integer', 'between:2,100'];
        }
        foreach (['bollinger.multiplier', 'atr.low_ratio', 'atr.high_ratio', 'adr.low_ratio', 'adr.extended_ratio'] as $path) {
            $rules['indicators.'.$path] = ['required', 'numeric', 'gt:0', 'max:5'];
        }
        foreach (['rsi.oversold', 'rsi.overbought'] as $path) {
            $rules['indicators.'.$path] = ['required', 'numeric', 'between:1,99'];
        }
        foreach (['strong_sell', 'sell', 'buy', 'strong_buy'] as $key) {
            $rules['thresholds.'.$key] = ['required', 'numeric', 'between:-100,100'];
        }
        $data = Validator::make($settings, $rules)->validate();
        $indicators = $data['indicators'];
        if ($indicators['ema']['fast'] >= $indicators['ema']['slow'] ||
            $indicators['macd']['fast'] >= $indicators['macd']['slow'] ||
            $indicators['rsi']['oversold'] >= $indicators['rsi']['overbought'] ||
            $indicators['atr']['low_ratio'] >= $indicators['atr']['high_ratio'] ||
            $indicators['adr']['low_ratio'] >= $indicators['adr']['extended_ratio']) {
            throw ValidationException::withMessages(['indicators' => 'Fast/slow, warning and range settings must be correctly ordered.']);
        }
        $t = $data['thresholds'];
        if (! ($t['strong_sell'] < $t['sell'] && $t['sell'] < 0 && 0 < $t['buy'] && $t['buy'] < $t['strong_buy'])) {
            throw ValidationException::withMessages(['thresholds' => 'Signal thresholds must be ordered around zero.']);
        }
        $sum = 0;
        foreach (self::DIRECTIONAL as $key) {
            if ($indicators[$key]['enabled']) {
            $sum += (float) $indicators[$key]['weight'];
            }
        }
        if ($sum <= 0 || $indicators['atr']['weight'] != 0 || $indicators['adr']['weight'] != 0) {
            throw ValidationException::withMessages(['indicators' => 'Enabled directional weights must have a positive sum; ATR and ADR weights must be zero.']);
        }

        return $data;
    }

    public static function save(array $settings, int $userId): array
    {
        $validated = self::validate($settings);

        return DB::transaction(function () use ($validated, $userId): array {
            $current = DB::table('market_insight_configurations')->orderByDesc('version')->lockForUpdate()->first();
            $version = (int) ($current?->version ?? 0) + 1;
            DB::table('market_insight_configurations')->insert([
                'version' => $version, 'settings' => json_encode($validated, JSON_THROW_ON_ERROR),
                'updated_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
            ]);

            return ['version' => $version, 'settings' => $validated];
        });
    }
}
