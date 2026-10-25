<?php

namespace App\Services;

use App\Models\AiKnowledgeBase;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TradingJournalAIContextBuilder
{
    public const INTENT_ADD = 'how_to_add_journal';

    public const INTENT_EDIT = 'how_to_edit_journal';

    public const INTENT_DELETE = 'how_to_delete_journal';

    public const INTENT_FIELD = 'explain_field';

    public const INTENT_STATISTICS = 'explain_statistics';

    public const INTENT_RISK = 'explain_risk';

    public const INTENT_RATING = 'explain_rating';

    public const INTENT_TROUBLESHOOT = 'troubleshoot';

    public const INTENT_DISCIPLINE = 'improve_journal_discipline';

    public const INTENT_EXPORT = 'export_journal';

    public const INTENT_SCREENSHOT = 'screenshot_help';

    public const INTENT_PERSONAL_STATS = 'user_personal_stats';

    public const INTENT_BEHAVIORAL_RISK = 'behavioral_risk';

    public const INTENT_REVENGE_TRADING = 'revenge_trading';

    public const INTENT_GAMBLING_TRADING = 'gambling_trading';

    public const INTENT_LAYERING_STRATEGY = 'layering_strategy';

    public const INTENT_UNSAFE = 'unsafe_trading_advice';

    public const INTENT_GENERAL = 'general_trading_journal';

    public function __construct(private readonly AiTradingStatisticsService $statisticsService)
    {
    }

    public function build(string $message, User $user): array
    {
        $intent = $this->detectIntent($message);
        $statisticsSummary = $this->shouldIncludePersonalStatistics($message, $intent)
            ? $this->statisticsService->summaryFor($user, $message, true)
            : null;

        $knowledge = $this->relevantKnowledge($message, $intent);

        return [
            'intent' => $intent,
            'knowledge' => $knowledge,
            'statistics' => $statisticsSummary,
            'system_context' => $this->systemContext(),
            'response_template' => $this->responseTemplate($intent),
            'few_shot_examples' => $this->fewShotExamples(),
        ];
    }

    public function detectIntent(string $message): string
    {
        $text = Str::lower($message);

        if ($this->isUnsafeTradingAdvice($text)) {
            return self::INTENT_UNSAFE;
        }

        if ($this->containsAny($text, ['screenshot', 'screen shot', 'image upload', 'upload image', 'chart picture', 'chart photo'])) {
            return self::INTENT_SCREENSHOT;
        }

        if ($this->containsAny($text, ['export', 'excel', 'report', 'download journal', 'download trades'])) {
            return self::INTENT_EXPORT;
        }

        if ($this->containsAny($text, ['revenge trading', 'revenge trade', 'revenge tier', 'post-loss', 'post loss', 'after loss', 'loss streak reaction'])) {
            return self::INTENT_REVENGE_TRADING;
        }

        if ($this->containsAny($text, ['gambling trading', 'gambling behaviour', 'gambling behavior', 'gambling tier', 'overtrading', 'over trading', 'rapid-fire', 'rapid fire', 'lot spike', 'oversized loss', 'high variance', 'margin pressure'])) {
            return self::INTENT_GAMBLING_TRADING;
        }

        if ($this->containsAny($text, ['layering', 'layer strategy', 'layered', 'same direction layer', 'stacked exposure', 'average down', 'averaging down', 'average up', 'averaging up'])) {
            return self::INTENT_LAYERING_STRATEGY;
        }

        if ($this->containsAny($text, ['behavioral risk', 'behavioural risk', 'behavior risk', 'behaviour risk', 'trading behavior', 'trading behaviour', 'risk profile', 'trading style', 'behavior penalty', 'behaviour penalty'])) {
            return self::INTENT_BEHAVIORAL_RISK;
        }

        if ($this->containsAny($text, ['add journal', 'add trading journal', 'add trade', 'create journal', 'create trading journal', 'create trade', 'new journal', 'new trading journal', 'record trade', 'insert trade', 'duplicate', 'multiple same'])) {
            return self::INTENT_ADD;
        }

        if ($this->containsAny($text, ['edit journal', 'edit trading journal', 'edit trade', 'update journal', 'update trading journal', 'update trade', 'change record', 'correct record'])) {
            return self::INTENT_EDIT;
        }

        if ($this->containsAny($text, ['delete journal', 'delete trading journal', 'delete trade', 'remove journal', 'remove trading journal', 'remove trade', 'duplicate record'])) {
            return self::INTENT_DELETE;
        }

        if ($this->containsAny($text, ['not showing', 'wrong', 'error', 'failed', 'validation', 'missing', 'cannot', "can't", 'empty data', 'not working'])) {
            return self::INTENT_TROUBLESHOOT;
        }

        if ($this->containsAny($text, ['my win rate', 'my stats', 'my statistics', 'my performance', 'my growth', 'my drawdown', 'this month', 'last month', 'my profit', 'my loss'])) {
            return self::INTENT_PERSONAL_STATS;
        }

        if ($this->containsAny($text, ['risk percentage', 'risk percent', 'risk reward', 'risk-reward', 'rrr', 'lot size', 'over risk', 'over-risk'])) {
            return self::INTENT_RISK;
        }

        if ($this->containsAny($text, ['rating', 'grade', 'score', 'quality', 'expectancy score', 'consistency score', 'trade quality'])) {
            return self::INTENT_RATING;
        }

        if ($this->containsAny($text, ['win rate', 'drawdown', 'growth', 'expectancy', 'consistency', 'profit/loss', 'profit loss', 'pnl', 'statistics', 'stats', 'balance'])) {
            return self::INTENT_STATISTICS;
        }

        if ($this->containsAny($text, ['discipline', 'mistake', 'emotion', 'psychology', 'revenge', 'overtrading', 'review losing', 'review winning', 'improve', 'habit'])) {
            return self::INTENT_DISCIPLINE;
        }

        if ($this->containsAny($text, ['field', 'meaning', 'what is', 'explain', 'entry price', 'exit price', 'pair', 'direction', 'result', 'notes', 'pips'])) {
            return self::INTENT_FIELD;
        }

        return self::INTENT_GENERAL;
    }

    public function isUnsafeTradingAdvice(string $message): bool
    {
        $text = Str::lower($message);

        foreach ([
            '/\bshould i\s+(buy|sell|long|short|trade)\b/',
            '/\b(buy|sell)\s+or\s+(buy|sell)\b/',
            '/\bgive me\b.*\bsignal\b/',
            '/\bentry\s+signal\b/',
            '/\bmarket\s+prediction\b/',
            '/\bpredict\b.*\bmarket\b/',
            '/\bwhat\s+should\s+i\s+trade\b/',
            '/\b(is|will)\b.*\b(go up|go down|rise|fall)\b/',
            '/\bwhere\s+should\s+i\s+(enter|exit)\b/',
            '/\bincrease\b.*\blot\b.*\b(recover|recovery|loss)\b/',
            '/\brecover\b.*\bloss\b.*\b(lot|leverage|risk)\b/',
            '/\bshould\s+i\b.*\b(layer|layering|average down|average up|martingale|double lot)\b/',
            '/\b(use|try)\b.*\b(martingale|double lot|layering|average down|average up)\b.*\b(recover|recovery|loss)\b/',
            '/\bhow\b.*\b(recover|win back)\b.*\b(loss|money)\b/',
        ] as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    private function relevantKnowledge(string $message, string $intent): Collection
    {
        if (! Schema::hasTable('ai_knowledge_bases')) {
            return collect();
        }

        $records = AiKnowledgeBase::active()
            ->whereIn('module', [
                AiKnowledgeBase::MODULE_TRADING_JOURNAL,
                AiKnowledgeBase::MODULE_TRADING_STATISTICS,
                AiKnowledgeBase::MODULE_GENERAL,
            ])
            ->latest()
            ->get();

        if ($records->isEmpty()) {
            return collect();
        }

        $tokens = $this->keywords($message);
        $intentKeywords = $this->intentKeywords($intent);

        $scored = $records->map(function (AiKnowledgeBase $record) use ($tokens, $intentKeywords): array {
            $haystack = Str::lower($record->module.' '.$record->title.' '.$record->content);
            $score = $record->module === AiKnowledgeBase::MODULE_TRADING_JOURNAL ? 6 : 1;

            foreach ($tokens as $token) {
                if (str_contains($haystack, $token)) {
                    $score += 3;
                }
            }

            foreach ($intentKeywords as $keyword) {
                if (str_contains($haystack, $keyword)) {
                    $score += 5;
                }
            }

            return ['record' => $record, 'score' => $score];
        })
            ->sortByDesc('score')
            ->values();

        return $scored
            ->filter(fn (array $row): bool => $row['score'] > 1)
            ->take(8)
            ->pluck('record')
            ->values();
    }

    private function systemContext(): array
    {
        return [
            'module' => 'Trading Journal',
            'routes' => [
                'list_page' => $this->routeContext('all.trading.journals', '/all/trading-journals', 'Trading Journal list and dashboard'),
                'add_page' => $this->routeContext('add.trading.journal', '/add/trading-journal', 'Add a new trade record'),
                'store_action' => $this->routeContext('store.trading.journal', '/store/trading-journal', 'Save a new trade record'),
                'edit_page' => $this->routeContext('edit.trading.journal', '/edit/trading-journal/{id}', 'Edit an existing trade record'),
                'update_action' => $this->routeContext('update.trading.journal', '/update/trading-journal/{id}', 'Save changes to a trade record'),
                'delete_action' => $this->routeContext('delete.trading.journal', '/delete/trading-journal/{id}', 'Delete a trade record'),
                'details_page' => $this->routeContext('trading.journal.details', '/trading-journal/details/{id}', 'View one trade record'),
                'export_excel' => $this->routeContext('trading-journal.export', '/trading-journal/download', 'Export current user journal to Excel'),
                'download_template' => $this->routeContext('download.trades.template', '/trading-journal/template/download', 'Download Excel import template'),
                'import_excel' => $this->routeContext('import.trading.journal', '/trading-journal/import', 'Import trades from Excel, XLS, or CSV'),
                'trading_pair_list' => $this->routeContext('all.trading.pairs', '/all/trading-pairs', 'Manage trading pairs'),
                'trading_pair_add' => $this->routeContext('add.trading.pair', '/add/trading-pair', 'Add a trading pair'),
                'capital_page' => $this->routeContext('capital.index', '/capital/all', 'Manage capital deposits and withdrawals'),
                'statistics_page' => $this->routeContext('all.trading.statistics', '/all/dashboard/trading-statistics', 'Trading Statistics dashboard'),
            ],
            'available_form_fields' => [
                'time_input_timezone' => 'Time source: Malaysia Time or MT5 platform time.',
                'time_input_offset_minutes' => 'MT5 server offset. Current form accepts UTC+2 or UTC+3 and converts to Malaysia Time.',
                'open_date' => 'Open trade time. Required.',
                'close_date' => 'Close trade time. Required and must be after open trade time.',
                'pair' => 'Trading pair selected from the trading_pairs table.',
                'direction' => 'Buy or Sell.',
                'entry_price' => 'Entry price. Required numeric value.',
                'exit_price' => 'Exit price. Required numeric value.',
                'lot_size' => 'Trade volume. Required numeric value, minimum 0.',
                'pips' => 'Calculated by the system from entry price, exit price, and the pair pip factor.',
                'profit_loss' => 'Calculated by the system from pips, lot size, and result.',
                'result' => 'Win, Loss, or Break Even.',
                'notes' => 'Free-text review notes for reason, emotion, mistake, lesson, setup, or psychology.',
                'duplicate_count' => 'Add form only. Creates multiple identical records, maximum 500, if capital balance will not go negative.',
            ],
            'not_currently_stored_as_separate_fields' => [
                'screenshot upload',
                'stop loss',
                'take profit',
                'risk percentage',
                'reward amount',
                'strategy',
                'setup',
                'session',
                'psychology',
                'mistake',
                'trade quality rating per trade',
            ],
            'calculation_rules' => [
                'pips' => 'abs(exit_price - entry_price) divided by the selected trading pair pip_factor, rounded by pip_decimal.',
                'profit_loss' => 'pips multiplied by lot_size multiplied by 10. Win makes it positive, Loss makes it negative, Break Even makes it 0.',
                'win_rate' => 'winning trades divided by winning plus losing trades, multiplied by 100. Breakeven trades are excluded from this denominator in the journal dashboard.',
                'growth_percent' => 'net profit/loss divided by total deposits, multiplied by 100. The journal dashboard sets growth to 0 when net profit/loss is not positive.',
                'drawdown_percent' => 'loss pressure compared with initial capital or peak equity depending on the report screen.',
                'expectancy' => '(win rate decimal x average win) - (loss rate decimal x average loss).',
                'consistency_score' => 'Best Day Rule checks whether the biggest winning day is 40% or less of total generated profit.',
                'risk_reward' => 'Journal dashboard currently uses total profit divided by total loss as an account-level risk reward/profit factor style metric.',
                'behavioral_risk_score' => 'max(gambling_score, revenge_score), capped at 100. Layering is reviewed separately and does not automatically increase gambling or revenge tier.',
                'revenge_trading_detection' => 'Looks for loss-chasing evidence such as recent loss, cooldown breach, opening within 5 minutes after loss, risk increase after loss, lot increase after loss, same-symbol pressure, same direction after stop loss, loss streak, daily loss limit breach, or emotional trading tags.',
                'gambling_trading_detection' => 'Looks for discipline breaks such as missing plan, missing setup when not standalone, missing entry reason, missing strategy, missing stop loss, risk above plan, setup risk above plan, overtrading, dangerous margin exposure, poor risk reward, no exit plan, or random symbol/strategy switching.',
                'layering_detection' => 'Looks for same-pair, same-direction orders opened close together or while earlier trades remain open. Planned layering is protected when it follows setup, stop-loss, risk, margin, cooldown, and daily-loss rules.',
            ],
            'behavioral_detection_context' => [
                'revenge_trading' => [
                    'meaning' => 'Emotionally reactive trading after a loss, often trying to win back the loss quickly.',
                    'system_checks' => [
                        'Last closed trade was a loss',
                        'New trade opened during configured cooldown after loss',
                        'New trade opened within 5 minutes after loss',
                        'Risk or lot size increased after loss',
                        'Same symbol and same direction pressure after stop loss',
                        'Loss streak or daily loss limit breach',
                    ],
                    'safe_guidance' => 'Explain it as a discipline warning only. Do not tell the user how to recover losses.',
                ],
                'gambling_trading' => [
                    'meaning' => 'High-variance behavior where trade activity or sizing looks more like betting than a planned process.',
                    'system_checks' => [
                        'Missing trade plan or setup when not standalone',
                        'Missing entry reason or strategy',
                        'Missing stop loss',
                        'Order risk or setup risk above plan',
                        'Overtrading or poor risk reward',
                        'Dangerous margin exposure or random symbols/strategy switching',
                    ],
                    'safe_guidance' => 'Focus on journal review, position-size discipline, trading plan rules, cooling-off periods, and risk control.',
                ],
                'layering_strategy' => [
                    'meaning' => 'Multiple same-pair, same-direction positions opened close together or overlapping.',
                    'system_checks' => [
                        'Same-pair and same-direction trades',
                        'Trade opens before the earlier same-direction trade closes',
                        'Layer added within the configured review window',
                        'Layer lot increases or adverse-price averaging appears',
                        'Valid planned layer has setup, stop loss, entry reason, strategy, safe margin, and controlled risk',
                        'Layering remains separate from final gambling/revenge score',
                    ],
                    'safe_guidance' => 'Explain detection and risk. Do not recommend layering as a signal or profit strategy.',
                ],
            ],
            'known_limits' => [
                'The current journal form does not upload screenshots.',
                'Stop loss, take profit, risk percentage, strategy, setup, session, mistake, and psychology are not separate database columns in the current journal model.',
                'Users can still write those review details in the Notes field.',
                'The AI is read-only and cannot create, update, delete, or query raw trade records directly.',
            ],
        ];
    }

    private function routeContext(string $name, string $fallbackPath, string $description): array
    {
        $params = $this->routePlaceholderParams($name);

        return [
            'route_name' => $name,
            'path' => Route::has($name) && $params === [] ? route($name, [], false) : $fallbackPath,
            'available' => Route::has($name),
            'description' => $description,
        ];
    }

    private function routePlaceholderParams(string $routeName): array
    {
        return match ($routeName) {
            'edit.trading.journal',
            'update.trading.journal',
            'delete.trading.journal',
            'trading.journal.details' => ['id' => '{id}'],
            default => [],
        };
    }

    private function responseTemplate(string $intent): string
    {
        return match ($intent) {
            self::INTENT_ADD,
            self::INTENT_EDIT,
            self::INTENT_DELETE,
            self::INTENT_EXPORT,
            self::INTENT_SCREENSHOT => "Title: Direct answer\nSteps:\n1.\n2.\n3.\nImportant notes:\nCommon mistakes:\nNeed more help:",
            self::INTENT_FIELD,
            self::INTENT_RISK,
            self::INTENT_RATING => "Meaning:\nWhy it matters:\nExample:\nCommon mistake:\nBest practice:",
            self::INTENT_STATISTICS,
            self::INTENT_PERSONAL_STATS => "Meaning:\nFormula:\nHow to interpret:\nWhat to review:\nRisk reminder:",
            self::INTENT_BEHAVIORAL_RISK,
            self::INTENT_REVENGE_TRADING,
            self::INTENT_GAMBLING_TRADING,
            self::INTENT_LAYERING_STRATEGY => "Meaning:\nHow the system detects it:\nWhat the user should review:\nHow to improve discipline:\nRisk reminder:",
            self::INTENT_TROUBLESHOOT => "Possible causes:\nWhat to check:\nHow to fix:\nIf still not working:",
            self::INTENT_DISCIPLINE => "Observation:\nWhat it may mean:\nHow to improve:\nJournal habit to build:\nRisk reminder:",
            default => "Direct answer:\nKey points:\nSteps or explanation:\nImportant notes:",
        };
    }

    private function fewShotExamples(): array
    {
        return [
            [
                'user' => 'How to add trading journal?',
                'assistant' => "To add a Trading Journal record:\n\n1. Go to Trading Journal.\n2. Click Add Journal or Add Trade.\n3. Select the trading pair.\n4. Fill in open/close time, direction, entry price, exit price, lot size, result, and notes.\n5. The system calculates pips and profit/loss.\n6. Click Save.\n\nImportant: Make sure your numbers are correct because statistics depend on this data.",
            ],
            [
                'user' => 'What is win rate?',
                'assistant' => "Win rate means the percentage of completed trades that ended as wins.\n\nFormula: winning trades divided by winning plus losing trades, multiplied by 100.\nExample: If you have 10 completed trades and 6 wins, your win rate is 60%.\n\nWin rate alone is not enough. Also review average win, average loss, risk-reward, drawdown, and consistency.",
            ],
            [
                'user' => 'Should I buy XAUUSD now?',
                'assistant' => "I can't provide buy/sell signals or financial advice.\n\nBut I can help you use the Trading Journal to record your trade idea, check your planned risk, and review whether it follows your trading plan.",
            ],
            [
                'user' => 'Why my statistics not showing?',
                'assistant' => "Possible reasons:\n\n1. No journal records have been added yet.\n2. Your selected date filter has no records.\n3. Some trades may not have result or profit/loss filled in.\n4. Capital/deposit may not be added if the statistic depends on capital.\n5. There may be incomplete records.\n\nPlease check your Trading Journal records and filters first.",
            ],
            [
                'user' => 'My win rate is high but still losing money, why?',
                'assistant' => "A high win rate does not always mean profit.\n\nPossible reasons:\n1. Losing trades are bigger than winning trades.\n2. Risk-reward may be too low.\n3. Winning trades may be closed too early.\n4. Losing trades may be allowed to run too far.\n5. Costs such as spread can affect results.\n\nUse the journal to compare average win, average loss, risk per trade, and trade quality.",
            ],
            [
                'user' => 'How do you detect revenge trading?',
                'assistant' => "Revenge trading detection looks for reactive behavior after a loss.\n\nThe system checks whether a new trade was opened soon after a losing trade, especially within 60 minutes or 15 minutes. It also checks if lot size increased, the same pair was traded again, direction flipped, or the trade came after a loss streak.\n\nThis is a discipline warning, not a trade signal.",
            ],
            [
                'user' => 'Am I gambling trading?',
                'assistant' => "I can explain your calculated behavior summary if the system provides it.\n\nThe journal reviews markers like overtrading days, rapid-fire entries, lot-size spikes, oversized losses, short holding time, layered exposure, and margin pressure.\n\nUse this as a review tool. It does not predict future results and it is not financial advice.",
            ],
            [
                'user' => 'What is layer strategy in my journal?',
                'assistant' => "Layering means opening multiple same-pair, same-direction trades close together or while earlier trades are still open.\n\nThe system flags overlap, fast layer additions, larger layer lot size, adverse-price averaging, and stacked active layers.\n\nThe journal can help you review whether layering followed a written plan or came from emotion. It should not be treated as a buy/sell signal.",
            ],
        ];
    }

    private function shouldIncludePersonalStatistics(string $message, string $intent): bool
    {
        if ($intent === self::INTENT_PERSONAL_STATS) {
            return true;
        }

        $text = Str::lower($message);

        return in_array($intent, [
            self::INTENT_STATISTICS,
            self::INTENT_TROUBLESHOOT,
            self::INTENT_RISK,
            self::INTENT_RATING,
            self::INTENT_BEHAVIORAL_RISK,
            self::INTENT_REVENGE_TRADING,
            self::INTENT_GAMBLING_TRADING,
            self::INTENT_LAYERING_STRATEGY,
        ], true)
            && $this->containsAny($text, ['my ', 'mine', 'me ', 'i ', 'am i', 'do i', 'this month', 'last month', 'myself']);
    }

    private function keywords(string $message): array
    {
        $stopWords = ['about', 'after', 'again', 'also', 'because', 'can', 'could', 'does', 'from', 'have', 'help', 'into', 'this', 'that', 'their', 'there', 'what', 'when', 'where', 'with', 'your'];

        return collect(preg_split('/[^a-z0-9_]+/', Str::lower($message)) ?: [])
            ->map(fn (string $word): string => trim($word))
            ->filter(fn (string $word): bool => strlen($word) >= 3 && ! in_array($word, $stopWords, true))
            ->unique()
            ->values()
            ->all();
    }

    private function intentKeywords(string $intent): array
    {
        return match ($intent) {
            self::INTENT_ADD => ['add', 'create', 'duplicate', 'multiple'],
            self::INTENT_EDIT => ['edit', 'update', 'change'],
            self::INTENT_DELETE => ['delete', 'remove', 'duplicate'],
            self::INTENT_FIELD => ['field', 'meaning', 'entry', 'exit', 'pair', 'result', 'notes'],
            self::INTENT_STATISTICS, self::INTENT_PERSONAL_STATS => ['win rate', 'drawdown', 'growth', 'expectancy', 'statistics', 'profit', 'loss'],
            self::INTENT_RISK => ['risk', 'risk reward', 'lot size'],
            self::INTENT_RATING => ['rating', 'score', 'quality', 'consistency', 'expectancy'],
            self::INTENT_BEHAVIORAL_RISK => ['behavior', 'behaviour', 'risk profile', 'trading style', 'behavioral risk'],
            self::INTENT_REVENGE_TRADING => ['revenge', 'post-loss', 'after loss', 'loss streak', 're-entry'],
            self::INTENT_GAMBLING_TRADING => ['gambling', 'overtrading', 'rapid', 'lot spike', 'oversized', 'margin pressure', 'high variance'],
            self::INTENT_LAYERING_STRATEGY => ['layering', 'layer strategy', 'stacked exposure', 'same-direction', 'average down'],
            self::INTENT_TROUBLESHOOT => ['wrong', 'missing', 'error', 'validation', 'empty', 'failed'],
            self::INTENT_DISCIPLINE => ['mistake', 'discipline', 'emotion', 'psychology', 'review'],
            self::INTENT_EXPORT => ['export', 'excel', 'report'],
            self::INTENT_SCREENSHOT => ['screenshot', 'image', 'upload'],
            default => ['trading journal'],
        };
    }

    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }
}
