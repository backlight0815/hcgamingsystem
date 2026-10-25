<?php

namespace App\Services\Ai;

use Illuminate\Validation\ValidationException;

final class MarketAnalystGenerationSchema
{
    public const FIELDS = [
        'title' => ['section' => 'report', 'max' => 255, 'generate' => true],
        'market_overview' => ['section' => 'market_structure', 'max' => 8000, 'generate' => true],
        'rsi_level' => ['section' => 'market_structure', 'max' => 255, 'generate' => true, 'verified_indicator' => 'rsi'],
        'order_block' => ['section' => 'market_structure', 'max' => 255, 'generate' => false],
        'key_zones' => ['section' => 'chinese_analysis', 'max' => 5000, 'generate' => true, 'needs_levels' => true],
        'entry_zones_description' => ['section' => 'chinese_analysis', 'max' => 5000, 'generate' => true],
        'analyst_view' => ['section' => 'chinese_analysis', 'max' => 5000, 'generate' => true],
        'strategy' => ['section' => 'chinese_analysis', 'max' => 5000, 'generate' => true],
        'chart_signals' => ['section' => 'chinese_analysis', 'max' => 5000, 'generate' => true, 'verified_only' => true],
        'trading_plan' => ['section' => 'execution_plan', 'max' => 12000, 'generate' => true],
    ];

    public static function select(array $input, array $context, array $verified = []): array
    {
        $mode = $input['mode'];
        $field = $input['field'] ?? null;
        $section = $input['section'] ?? null;
        if ($field && ! isset(self::FIELDS[$field])) {
        throw ValidationException::withMessages(['field' => 'Invalid AI field.']);
        }
        $selected = [];
        foreach (self::FIELDS as $name => $meta) {
            if ($field && $name !== $field) {
            continue;
            }
            if ($section && $meta['section'] !== $section) {
            continue;
            }
            if (! $meta['generate'] && $mode !== 'improve') {
            continue;
            }
            if ($mode !== 'improve' && ($meta['verified_only'] ?? false) && empty($verified['frames'])) {
            continue;
            }
            if ($mode !== 'improve' && isset($meta['verified_indicator'])
                && ! collect($verified['frames'] ?? [])->contains(fn ($frame) => isset($frame['indicators'][$meta['verified_indicator']]))) {
            continue;
            }
            if ($mode === 'improve' && trim((string) ($input[$name] ?? '')) === '') {
            continue;
            }
            if ($mode === 'empty' && trim((string) ($input[$name] ?? '')) !== '') {
            continue;
            }
            if (($meta['needs_levels'] ?? false) && ! $context['supports'] && ! $context['resistances']) {
            continue;
            }
            $selected[$name] = $meta;
        }
        if (! $selected) {
        throw ValidationException::withMessages(['field' => 'No eligible fields have enough source context for this action.']);
        }

        return $selected;
    }

    public static function schema(array $selected, array $planSchema): array
    {
        $fields = array_diff(array_keys($selected), ['trading_plan']);
        $properties = [];
        foreach ($fields as $field) {
        $properties[$field] = ['type' => 'string'];
        }
        $result = ['type' => 'object', 'properties' => []];
        if ($fields) {
        $result['properties']['fields'] = ['type' => 'object', 'properties' => $properties, 'required' => array_values($fields)];
        }
        if (isset($selected['trading_plan'])) {
        $result['properties']['plan'] = $planSchema;
        }
        $result['required'] = array_keys($result['properties']);

        return $result;
    }
}
