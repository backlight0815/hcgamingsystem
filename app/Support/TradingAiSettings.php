<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TradingAiSettings
{
    public const BUILD = 2;

    public static function get(): array
    {
        $settings = Schema::hasTable('ai_chat_settings')
            ? DB::table('ai_chat_settings')->pluck('value', 'key') : collect();
        if (Schema::hasTable('ai_chat_response_settings')) {
            $settings = $settings->merge(DB::table('ai_chat_response_settings')->pluck('value', 'key'));
        }
        $maximum = min(1, max(.1, (float) ($settings['maximum_risk_percent'] ?? 1)));
        return [
            'build' => self::BUILD,
            'default_risk_percent' => min($maximum, max(.01, (float) ($settings['default_risk_percent'] ?? .5))),
            'maximum_risk_percent' => $maximum,
            'minimum_rr' => max(1, (float) ($settings['minimum_rr'] ?? 2)),
            'daily_request_limit' => max(1, (int) ($settings['daily_request_limit'] ?? 100)),
            'retrieval_limit' => min(8, max(1, (int) ($settings['retrieval_limit'] ?? 4))),
            'model' => (string) ($settings['model'] ?? config('services.gemini.model')),
            'retention_days' => max(7, (int) ($settings['retention_days'] ?? 90)),
            'operation_mode' => in_array($settings['operation_mode'] ?? 'hybrid', ['knowledge_only', 'hybrid', 'generative'], true)
                ? ($settings['operation_mode'] ?? 'hybrid') : 'hybrid',
            'external_enabled' => filter_var($settings['external_enabled'] ?? '1', FILTER_VALIDATE_BOOLEAN),
            'ai_name' => (string) ($settings['ai_name'] ?? 'HC TradingHub Assistant'),
            'ai_role' => (string) ($settings['ai_role'] ?? 'Trading education and customer support assistant'),
            'tone' => in_array($settings['tone'] ?? 'educational', ['professional', 'friendly', 'educational', 'technical'], true)
                ? ($settings['tone'] ?? 'educational') : 'educational',
            'response_length' => in_array($settings['response_length'] ?? 'balanced', ['short', 'balanced', 'detailed'], true)
                ? ($settings['response_length'] ?? 'balanced') : 'balanced',
            'language_fallback' => in_array($settings['language_fallback'] ?? 'en', ['en', 'none'], true)
                ? ($settings['language_fallback'] ?? 'en') : 'en',
            'response_rules' => (string) ($settings['response_rules'] ?? 'Answer from published, approved TradingHub knowledge. Explain terms clearly. Ask for clarification when needed. Never invent live prices, account data, policies or guaranteed profits. Escalate unsupported customer support questions.'),
        ];
    }
}
