<?php

namespace App\Support;

use App\Services\TradingIdeaRiskCalculator;
use InvalidArgumentException;

/** A small, provider-free planner for compound and referential client questions. */
class TradingQuestionPlanner
{
    public function __construct(
        private readonly TradingConceptResolver $concepts,
        private readonly TradingIdeaRiskCalculator $calculator,
    ) {}

    public function plan(string $question, array $history): array
    {
        $resolved = $this->concepts->resolve($question);
        $ids = array_column($resolved, 'id');
        preg_match_all('/\b(?:XAUUSD|XAGUSD|EURUSD|GBPUSD|USDJPY|BTCUSD|ETHUSD)\b/i', $question, $symbolMatches);
        preg_match_all('/\b(?:H4|H1|M15|D1)\b/i', $question, $frameMatches);
        $symbols = array_values(array_unique(array_map('strtoupper', $symbolMatches[0])));
        $frames = array_values(array_unique(array_map('strtoupper', $frameMatches[0])));
        $referential = (bool) preg_match('/^(?:what about|and (?:my|the)|is that|what did you say about)\b/i', trim($question));
        $previous = $referential ? $this->lastUserMessage($history) : null;
        $previousSymbol = $previous && preg_match('/\b(?:XAUUSD|XAGUSD|EURUSD|GBPUSD|USDJPY|BTCUSD|ETHUSD)\b/i', $previous, $match)
            ? strtoupper($match[0]) : null;
        if ($symbols === [] && $previousSymbol) $symbols = [$previousSymbol];
        $parts = [];
        foreach (['break_even', 'stop_loss', 'take_profit', 'risk_reward', 'bos', 'choch', 'liquidity_sweep',
            'option_assignment', 'zero_dte', 'futures_roll', 'crypto_mark_price', 'backtest_bias'] as $id) {
            if (in_array($id, $ids, true)) $parts[] = $id;
        }
        if (preg_match('/\b(?:lot size|position size|lots?|positions?|orders?)\b/i', $question)) $parts[] = 'position_sizing';
        $parts = array_values(array_unique($parts));
        $needsSpecs = in_array('position_sizing', $parts, true)
            && ! preg_match('/\b(?:tick size|tick value|volume step|contract specification)\b/i', $question);
        $current = (bool) preg_match('/\b(?:current|live|right now|today|now)\b/i', $question);
        return [
            'sub_intents' => $parts, 'entities' => ['concepts' => $resolved, 'symbols' => $symbols, 'timeframes' => $frames],
            'required_evidence' => $current ? ['verified_fresh_market_data'] : [],
            'available_evidence' => $previousSymbol && $referential ? ['previous_user_symbol' => $previousSymbol] : [],
            'missing_required_inputs' => $needsSpecs ? ['verified broker tick/contract specifications, costs and account equity'] : [],
            'calculation_jobs' => in_array('break_even', $parts, true) && preg_match('/\b\d+(?:\.\d+)?\b/', $question) ? ['price_distance_r'] : [],
            'safety_flags' => ['current_market_request' => $current, 'referential' => $referential,
                'context_symbol_overridden' => $previousSymbol && preg_match('/\b(?:XAUUSD|XAGUSD|EURUSD|GBPUSD|USDJPY|BTCUSD|ETHUSD)\b/i', $question)
                    && $symbols[0] !== $previousSymbol],
            'previous_user_message' => $previous,
            'ambiguous' => $this->concepts->ambiguous($question),
        ];
    }

    public function directAnswer(string $question, array $plan): ?array
    {
        $ids = $plan['sub_intents'];
        $ambiguous = $plan['ambiguous'];
        if ($ambiguous && count($ids) < 2
            && ! ($ambiguous['term'] === 'stop'
                && (in_array('stop_loss', $ids, true) || preg_match('/\bstop losses?\b/i', $question)))) {
            return $this->answer('By "'.$ambiguous['term'].'", do you mean '.implode(', ', $ambiguous['meanings']).'?', 'clarification');
        }
        if (preg_match('/\b(20\d{2}-\d{2}-\d{2})\b/', $question, $date)
            && preg_match('/\b(?:yesterday|already released|occurred|last week)\b/i', $question)
            && \Carbon\Carbon::parse($date[1])->isFuture()) {
            return $this->answer('That date is in the future, so I cannot treat the described event as an observed release. Please correct the date or provide the original timestamped source.', 'conflicting_inputs');
        }
        if (in_array('bos', $ids, true) && in_array('liquidity_sweep', $ids, true)
            && preg_match('/(?:same|equal|=|identical|等于|一樣)/iu', $question)) {
            return $this->answer('BOS (break of structure) and a liquidity sweep are not the same. In HC TradingHub\'s framework, BOS needs a confirmed close beyond the relevant swing in the trend direction. A sweep can pierce the level and close back inside. Check the timeframe, close and subsequent structure before interpreting either; a wick alone is not a confirmed BOS.', 'concept_distinction');
        }
        if (in_array('liquidation', array_column($plan['entities']['concepts'], 'id'), true)
            && in_array('margin_call', array_column($plan['entities']['concepts'], 'id'), true)) {
            return $this->answer('A margin call is a request or warning to restore required collateral under a product\'s rules. Liquidation is the closing of some or all of a position under the venue or broker process. They are related but not synonymous: some products can liquidate rapidly without a separate actionable call, and a call does not always mean liquidation already occurred. Verify the exact contract and margin policy.', 'concept_distinction');
        }
        if (preg_match('/\b(?:gold|XAUUSD)\b.*\b(?:lot|pip|tick)\b|\b(?:lot|pip|tick)\b.*\b(?:gold|XAUUSD)\b/i', $question)
            && preg_match('/\b(?:how much|usd|dollar|worth|value|size|1 lot|one lot)\b/i', $question)
            && ! (in_array('break_even', $ids, true) && in_array('position_sizing', $ids, true))) {
            return $this->answer('I cannot turn a gold/XAUUSD lot or pip into a USD amount without the exact broker product, contract size, tick size and tick value, quote/account currency, conversion and costs. A gold CFD lot and a futures contract are not interchangeable. Share the broker contract specification and account risk budget for a calculation; I will not guess a USD-per-pip figure.', 'missing_contract_spec');
        }
        if (preg_match('/\b(?:short|sell)\b/i', $question)
            && preg_match('/\b(?:entry|sold|sell)\s*(?:at\s*)?(\d+(?:\.\d+)?)/i', $question, $entry)
            && preg_match('/\b(?:SL|stop(?: loss)?)\s*(?:at\s*)?(\d+(?:\.\d+)?)/i', $question, $stop)
            && (float) $stop[1] <= (float) $entry[1]) {
            return $this->answer('For a short position, the protective stop normally belongs above the entry. You gave a stop at or below entry, so please correct the side or prices before I calculate risk.', 'conflicting_inputs');
        }
        if (in_array('break_even', $ids, true) && in_array('position_sizing', $ids, true)
            && preg_match('/\b(?:bought|buy|long)\b.{0,35}?\b(?:at\s*)?(\d+(?:\.\d+)?)/i', $question, $entry)
            && preg_match('/\b(?:SL|stop(?: loss)?)\s*(?:at\s*)?(\d+(?:\.\d+)?)/i', $question, $stop)
            && preg_match('/\b(?:hits?|reaches?|at)\s*(\d+(?:\.\d+)?)/i', substr($question, strpos($question, $stop[0]) + strlen($stop[0])), $observed)) {
            try {
                $r = $this->calculator->priceDistanceR($entry[1], $stop[1], $observed[1], 'buy');
            } catch (InvalidArgumentException) {
                return $this->answer('The entry and stop conflict for a long position. Please correct the prices before calculating R.', 'conflicting_inputs');
            }
            $count = preg_match('/\b(?:five|5)\s+(?:positions?|orders?)\b/i', $question) ? 'Five related positions share one idea risk budget. ' : 'Related entries share one idea risk budget. ';
            $answer = 'For this hypothetical long, entry '.$entry[1].' and SL '.$stop[1].' make 1R a '.((float) $entry[1] - (float) $stop[1]).' price-unit move. At '.$observed[1].' the move is '.$r.'R before costs. Moving to BE (your term for a stop near entry) is optional at about +1R and depends on structure and a tested plan; entry-price BE can still lose fees or slip. '.$count.'I cannot calculate a safe lot size without account equity, chosen total risk, verified broker tick/contract specifications, currency conversion and costs. What are those specifications and your account equity?';
            return $this->answer($answer, 'compound_risk', [[
                'claim_type' => 'calculation', 'statement' => 'Hypothetical price-distance move: '.$r.'R',
                'evidence_refs' => [], 'as_of' => null, 'instrument_and_venue' => null,
                'derivation_id' => 'TradingIdeaRiskCalculator::priceDistanceR',
                'verification_status' => 'qualified', 'client_display_policy' => 'show',
            ]]);
        }
        if (preg_match('/^what did you say about my SL\??$/i', trim($question))) {
            $previous = $plan['previous_user_message'];
            if ($previous && preg_match('/\b(?:SL|stop(?: loss)?)\s*(?:at\s*)?(\d+(?:\.\d+)?)/i', $previous, $stop)) {
                return $this->answer('In this conversation, you described your SL as '.$stop[1].'. That was your stated level, not a verified current market price or a confirmation that it is structurally valid.', 'conversation_reference');
            }
            return $this->answer('I cannot find a clear SL level in the recent questions in this conversation. Please restate the position, entry and stop.', 'clarification');
        }
        if (preg_match('/^what about\s+(H4|H1|M15)\??$/i', trim($question), $frame)) {
            $symbol = $plan['entities']['symbols'][0] ?? null;
            if (! $symbol) return $this->answer('Which instrument and prior scenario do you mean for '.strtoupper($frame[1]).'?', 'clarification');
            return $this->answer('For '.$symbol.' '.strtoupper($frame[1]).', I can explain the structure to check, but this conversation has no verified current chart. Please provide timestamped swings or OHLCV for that timeframe; do not treat an earlier hypothetical price as a live quote.', 'context_followup');
        }
        if (preg_match('/^what about\s+(XAUUSD|XAGUSD|EURUSD|GBPUSD|USDJPY|BTCUSD|ETHUSD)\s+(H4|H1|M15)\??$/i', trim($question), $newContext)) {
            return $this->answer('For '.strtoupper($newContext[1]).' '.strtoupper($newContext[2]).', I will not carry over another instrument\'s pip, tick, contract or hypothetical price assumptions. Please provide a timestamped chart or OHLCV and this product\'s specifications for a conditional review.', 'context_switch');
        }
        if (in_array('break_even', $ids, true) && in_array('stop_loss', $ids, true)
            && (preg_match('/\p{Han}/u', $question) || preg_match('/\b(?:lepas|macam|pulang)\b/iu', $question))) {
            $answer = preg_match('/\p{Han}/u', $question)
                ? 'BE／保本是考虑把SL止损移近入场价；入场价不等于保证零亏损，点差、费用和滑点仍可能造成损失。先看结构和已测试的计划，约+1R只是可考虑的条件，不是自动规则。没有已核实的当前图表，我不能给出具体移动价位。'
                : 'BE (pulang modal) bermaksud mempertimbangkan SL dekat harga masuk. Ia bukan jaminan rugi sifar kerana spread, fi dan gelinciran. Semak struktur dan pelan yang diuji; sekitar +1R ialah pertimbangan, bukan arahan automatik. Tanpa carta semasa yang disahkan, saya tidak boleh beri aras tepat.';
            return $this->answer($answer, 'multilingual_explanation');
        }
        if (in_array('break_even', $ids, true) && in_array('bos', $ids, true)
            && preg_match('/\b(?:lepas|macam|set)\b/iu', $question)) {
            return $this->answer('BOS menunjukkan pecahan struktur yang perlu disahkan pada rangka masa yang disebut. BE (pulang modal) ialah keputusan pengurusan SL, bukan akibat automatik BOS. Untuk Gold M15, semak harga penutupan, struktur H1, jarak ke SL, kos dan pelan yang diuji; tanpa carta semasa saya tidak boleh menetapkan aras BE.', 'compound_structure');
        }
        return null;
    }

    private function lastUserMessage(array $history): ?string
    {
        foreach (array_reverse($history) as $item) {
            if (($item->role ?? $item['role'] ?? null) === 'user') return (string) ($item->content ?? $item['content'] ?? '');
        }
        return null;
    }

    private function answer(string $text, string $intent, array $claims = []): array
    {
        return ['answer' => $text, 'sources' => [], 'guarded' => true, 'intent' => $intent,
            'response_source' => 'fallback', 'answer_type' => $intent === 'clarification' ? 'unable_to_verify' : 'educational_explanation',
            'unanswered' => false, 'claim_evidence' => $claims];
    }
}
