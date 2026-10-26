<?php

namespace App\Support;

class TradingConceptResolver
{
    public function resolve(string $question): array
    {
        $found = [];
        foreach (config('trading_ai_concepts.concepts', []) as $id => $entry) {
            $aliases = $entry['aliases'] ?? [];
            usort($aliases, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));
            foreach ($aliases as $alias) {
                $caseSensitive = in_array($alias, ['BE', 'SL', 'TP', 'RR', 'BOS', 'CHOCH'], true);
                $pattern = '/(?<![\p{L}\p{N}])'.preg_quote($alias, '/').'(?![\p{L}\p{N}])/u'.($caseSensitive ? '' : 'i');
                if (preg_match($pattern, $question, $matches)) {
                    $found[$id] = ['id' => $id, 'matched' => $matches[0],
                        'version' => config('trading_ai_concepts.version'), 'confidence' => 1.0,
                        'related' => $entry['related'] ?? [], 'not_equivalent' => $entry['not_equivalent'] ?? []];
                    break;
                }
            }
        }
        return array_values($found);
    }

    public function ambiguous(string $question): ?array
    {
        foreach (config('trading_ai_concepts.ambiguous', []) as $term => $meanings) {
            if (! preg_match('/(?<![\p{L}\p{N}])'.preg_quote($term, '/').'(?![\p{L}\p{N}])/iu', $question)) continue;
            if (preg_match('/\b(?:initial|free|level|profit|bid|ask|option|calendar|stop.loss|stop.market|stop.limit|futures|CFD|traded|contract size|order quantity|margin call)\b/i', $question)) continue;
            return ['term' => $term, 'meanings' => $meanings];
        }
        return null;
    }
}
