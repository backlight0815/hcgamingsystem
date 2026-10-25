<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TradingAiSettings
{
    public static function get(): array
    {
        $settings = Schema::hasTable('ai_chat_settings')
            ? DB::table('ai_chat_settings')->pluck('value', 'key') : collect();
        $maximum = min(1, max(.1, (float) ($settings['maximum_risk_percent'] ?? 1)));
        return [
            'default_risk_percent' => min($maximum, max(.01, (float) ($settings['default_risk_percent'] ?? .5))),
            'maximum_risk_percent' => $maximum,
            'minimum_rr' => max(1, (float) ($settings['minimum_rr'] ?? 2)),
            'daily_request_limit' => max(1, (int) ($settings['daily_request_limit'] ?? 100)),
            'retrieval_limit' => min(8, max(1, (int) ($settings['retrieval_limit'] ?? 4))),
            'model' => (string) ($settings['model'] ?? config('services.gemini.model')),
            'retention_days' => max(7, (int) ($settings['retention_days'] ?? 90)),
        ];
    }
}
