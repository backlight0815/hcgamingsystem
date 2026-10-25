<?php

namespace App\Services;

class MarketStructureEvaluator
{
    public function evaluate(array $frames): array
    {
        $h4 = $frames['h4'] ?? null;
        $h1 = $frames['h1'] ?? null;
        $m15 = $frames['m15'] ?? null;
        $warnings = [];
        foreach (['H4' => $h4, 'H1' => $h1, 'M15' => $m15] as $name => $value) {
            if ($value === null || $value === 'unknown') $warnings[] = $name.' structure is missing.';
        }
        if ($h4 === null || $h4 === 'unknown' || $h1 === null || $h1 === 'unknown') {
            return ['classification' => 'Insufficient data', 'major_bias' => $h4,
                'internal_bias' => $h1, 'explanation' => 'A full multi-timeframe bias requires H4 and H1 evidence.',
                'warnings' => $warnings];
        }
        if (($frames['range_middle'] ?? false) || $h4 === 'ranging' || $h1 === 'ranging') {
            return ['classification' => 'No Trade', 'major_bias' => $h4, 'internal_bias' => $h1,
                'explanation' => 'Range-middle or unclear directional structure does not justify an execution setup.',
                'warnings' => $warnings];
        }
        if ($frames['h1_invalidated'] ?? false) {
            return ['classification' => 'Wait for confirmation', 'major_bias' => $h4, 'internal_bias' => 'reassessment required',
                'explanation' => 'H1 protected structure is invalidated; reassess the original directional bias before considering a setup.',
                'warnings' => $warnings];
        }
        if ($h4 !== $h1) {
            return ['classification' => 'Wait for confirmation', 'major_bias' => $h4, 'internal_bias' => $h1,
                'explanation' => 'H1 is counter to H4. Label any H1 idea countertrend and require additional confirmation.',
                'warnings' => $warnings];
        }
        if (($m15 === 'bearish_choch' && $h1 === 'bullish') || ($m15 === 'bullish_choch' && $h1 === 'bearish')) {
            return ['classification' => 'Wait for confirmation', 'major_bias' => $h4, 'internal_bias' => $h1,
                'explanation' => 'An M15 CHOCH against H1 may be a correction. It does not confirm an H1 or H4 reversal. Wait for an H1 zone and a confirmed M15 reaction aligned with the larger structure.',
                'warnings' => $warnings];
        }
        if ($m15 === null || $m15 === 'unknown' || ! ($frames['at_h1_zone'] ?? false) || ! ($frames['m15_reaction_confirmed'] ?? false)) {
            return ['classification' => 'Wait for confirmation', 'major_bias' => $h4, 'internal_bias' => $h1,
                'explanation' => 'Directional bias is not an executable setup. Confirm an H1 zone reaction and M15 structure; a level touch alone is insufficient.',
                'warnings' => $warnings];
        }
        return ['classification' => ucfirst($h1).' educational scenario', 'major_bias' => $h4,
            'internal_bias' => $h1, 'explanation' => 'H4 and H1 align and an M15 reaction is reported. Structural stop, opposing target, costs and risk still need separate validation.',
            'warnings' => $warnings];
    }
}
