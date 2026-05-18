<?php

namespace App\Http\Controllers\Trading;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\MarketAnalysis;
use App\Models\MarketOutlookDiscord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class MarketAnalystController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureMarketManager();

        $query = MarketAnalysis::with('community')
            ->latest('analysis_date')
            ->latest();

        $this->applyFilters($query, $request);

        $analyses = $query->paginate(15)->withQueryString();
        $communities = Community::where('status', 1)->orderBy('name')->get();
        $markets = MarketAnalysis::select('market')
            ->whereNotNull('market')
            ->distinct()
            ->orderBy('market')
            ->pluck('market');

        $totalOutlook = MarketAnalysis::count();
        $sentOutlook = MarketAnalysis::where('discord_sent', true)->count();
        $weeklyOutlook = MarketAnalysis::where('created_at', '>=', now()->subDays(7))->count();
        $unsentOutlook = MarketAnalysis::where('discord_sent', false)->count();

        $breadcrumbData = $this->breadcrumbs([
            ['label' => 'Market Analyst', 'url' => route('market-analyst.index')],
        ]);

        return view('admin.market_analyst.market_analyst_all', compact(
            'analyses',
            'breadcrumbData',
            'communities',
            'markets',
            'totalOutlook',
            'sentOutlook',
            'weeklyOutlook',
            'unsentOutlook'
        ));
    }

    public function create()
    {
        $this->ensureMarketManager();

        $communities = Community::where('status', 1)->orderBy('name')->get();
        $breadcrumbData = $this->breadcrumbs([
            ['label' => 'Market Analyst', 'url' => route('market-analyst.index')],
            ['label' => 'Add Analysis', 'url' => route('market-analyst.create')],
        ]);

        return view('admin.market_analyst.marketanalyst_add', compact('communities', 'breadcrumbData'));
    }

    public function store(Request $request)
    {
        $this->ensureMarketManager();

        $data = $this->validatedMarketAnalysisData($request);
        $communityIds = $this->selectedCommunityIds($request->input('community_id'));

        if (empty($communityIds)) {
            return back()
                ->withInput()
                ->with([
                    'message' => 'Please select at least one active community.',
                    'alert-type' => 'error',
                ]);
        }

        $data = $this->prepareMarketAnalysisData($request, $data);

        foreach ($communityIds as $communityId) {
            MarketAnalysis::create(array_merge($data, [
                'community_id' => $communityId,
                'Outlook_Code' => $this->generateUniqueOutlookCode(),
            ]));
        }

        return redirect()
            ->route('market-analyst.index')
            ->with([
                'message' => 'Market analysis created successfully.',
                'alert-type' => 'success',
            ]);
    }

    public function show($id)
    {
        $this->ensureMarketManager();

        $analysis = MarketAnalysis::with('community')->findOrFail($id);
        $breadcrumbData = $this->breadcrumbs([
            ['label' => 'Market Analyst', 'url' => route('market-analyst.index')],
            ['label' => 'View Details', 'url' => route('market-analyst.show', $analysis->id)],
        ]);

        return view('admin.market_analyst.market_analyst_view', compact('analysis', 'breadcrumbData'));
    }

    public function edit($id)
    {
        $this->ensureMarketManager();

        $analysis = MarketAnalysis::findOrFail($id);
        $communities = Community::where('status', 1)->orderBy('name')->get();
        $selectedTrend = $this->resolveTrendKey($analysis->trend_structure);
        $selectedStrength = $this->resolveStrengthKey($analysis->trend_strength);

        $breadcrumbData = $this->breadcrumbs([
            ['label' => 'Market Analyst', 'url' => route('market-analyst.index')],
            ['label' => 'Edit Analysis', 'url' => route('market-analyst.edit', $analysis->id)],
        ]);

        return view('admin.market_analyst.market_analyst_edit', compact(
            'analysis',
            'communities',
            'selectedTrend',
            'selectedStrength',
            'breadcrumbData'
        ));
    }

    public function update(Request $request, $id)
    {
        $this->ensureMarketManager();

        $analysis = MarketAnalysis::findOrFail($id);
        $data = $this->validatedMarketAnalysisData($request, true);
        $data = $this->prepareMarketAnalysisData($request, $data, $analysis);
        $data['discord_sent'] = false;

        $analysis->update($data);

        return redirect()
            ->route('market-analyst.index')
            ->with([
                'message' => 'Market analysis updated successfully.',
                'alert-type' => 'success',
            ]);
    }

    public function destroy($id)
    {
        $this->ensureMarketManager();

        $analysis = MarketAnalysis::findOrFail($id);
        $analysis->delete();

        return redirect()
            ->route('market-analyst.index')
            ->with([
                'message' => 'Market analysis deleted successfully.',
                'alert-type' => 'success',
            ]);
    }

    public function sendToDiscord($id)
    {
        $this->ensureMarketManager();

        if (! feature_enabled('DiscordIntegration')) {
            return back()->with([
                'message' => 'Discord Integration is currently disabled.',
                'alert-type' => 'error',
            ]);
        }

        $analysis = MarketAnalysis::with(['community', 'discordMessages'])->findOrFail($id);
        $community = $analysis->community;

        if (! $community || ! $community->status || empty($community->discord_webhook_outlook)) {
            return back()->with([
                'message' => 'This analysis does not have an active community with an outlook webhook.',
                'alert-type' => 'error',
            ]);
        }

        $message = $this->composeDiscordMessage($analysis, $community);
        $discordMessage = $message;

        if (feature_enabled('DiscordIntegration_Everyone') && $community->discord_everyone_enabled) {
            $discordMessage = "@everyone\n\n" . $discordMessage;
        }

        $allowedMentions = feature_enabled('DiscordIntegration_Everyone') && $community->discord_everyone_enabled
            ? ['parse' => ['everyone']]
            : ['parse' => []];

        $discordRecord = MarketOutlookDiscord::where('outlook_id', $analysis->id)
            ->where('community_id', $community->id)
            ->first();

        try {
            if ($discordRecord && $discordRecord->message_id) {
                $response = Http::patch($community->discord_webhook_outlook . "/messages/{$discordRecord->message_id}", [
                    'content' => $discordMessage,
                    'allowed_mentions' => $allowedMentions,
                ]);
            } else {
                $filePath = $analysis->outlook_image ? public_path($analysis->outlook_image) : null;

                if ($filePath && File::exists($filePath)) {
                    $response = Http::attach(
                        'file',
                        File::get($filePath),
                        basename($filePath)
                    )->post($community->discord_webhook_outlook . '?wait=true', [
                        'content' => $discordMessage,
                        'allowed_mentions' => $allowedMentions,
                    ]);
                } else {
                    $response = Http::post($community->discord_webhook_outlook . '?wait=true', [
                        'content' => $discordMessage,
                        'allowed_mentions' => $allowedMentions,
                    ]);
                }
            }

            if (! $response->successful()) {
                throw new \RuntimeException('Discord returned HTTP ' . $response->status());
            }

            $discordData = $response->json();

            MarketOutlookDiscord::updateOrCreate(
                ['outlook_id' => $analysis->id, 'community_id' => $community->id],
                [
                    'community' => $community->name,
                    'message_id' => $discordData['id'] ?? optional($discordRecord)->message_id,
                    'channel_id' => $discordData['channel_id'] ?? optional($discordRecord)->channel_id,
                ]
            );

            $analysis->update(['discord_sent' => true]);

            return back()->with([
                'message' => 'Market analysis sent or updated on Discord.',
                'alert-type' => 'success',
            ]);
        } catch (\Throwable $exception) {
            \Log::error('Market analysis Discord send failed', [
                'analysis_id' => $analysis->id,
                'community_id' => $community->id,
                'error' => $exception->getMessage(),
            ]);

            return back()->with([
                'message' => 'Discord send failed: ' . $exception->getMessage(),
                'alert-type' => 'error',
            ]);
        }
    }

    public function traderIndex(Request $request)
    {
        $this->ensureTradingReader();

        $query = MarketAnalysis::with('community')
            ->whereHas('community', fn ($builder) => $builder->where('status', 1))
            ->latest('analysis_date')
            ->latest();

        $this->applyFilters($query, $request);

        if ($request->filled('structure')) {
            $query->where('trend_structure', 'like', '%' . $request->input('structure') . '%');
        }

        $latestAnalysis = (clone $query)->first();
        $analyses = $query->paginate(9)->withQueryString();
        $markets = MarketAnalysis::whereHas('community', fn ($builder) => $builder->where('status', 1))
            ->select('market')
            ->whereNotNull('market')
            ->distinct()
            ->orderBy('market')
            ->pluck('market');

        $totalOutlook = MarketAnalysis::whereHas('community', fn ($builder) => $builder->where('status', 1))->count();
        $weeklyOutlook = MarketAnalysis::whereHas('community', fn ($builder) => $builder->where('status', 1))
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $breadcrumbData = $this->breadcrumbs([
            ['label' => 'Market Analyst', 'url' => route('trading.market-analyst.index')],
        ]);

        return view('traders.market_analyst.index', compact(
            'analyses',
            'latestAnalysis',
            'markets',
            'totalOutlook',
            'weeklyOutlook',
            'breadcrumbData'
        ));
    }

    public function traderShow(MarketAnalysis $analysis)
    {
        $this->ensureTradingReader();
        abort_unless($analysis->community && $analysis->community->status, 404);

        $analysis->load('community');

        $relatedAnalyses = MarketAnalysis::with('community')
            ->where('id', '!=', $analysis->id)
            ->where('market', $analysis->market)
            ->whereHas('community', fn ($builder) => $builder->where('status', 1))
            ->latest('analysis_date')
            ->take(3)
            ->get();

        $breadcrumbData = $this->breadcrumbs([
            ['label' => 'Market Analyst', 'url' => route('trading.market-analyst.index')],
            ['label' => $analysis->title, 'url' => route('trading.market-analyst.show', $analysis->id)],
        ]);

        return view('traders.market_analyst.show', compact('analysis', 'relatedAnalyses', 'breadcrumbData'));
    }

    private function validatedMarketAnalysisData(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'community_id' => $updating
                ? ['required', 'integer', Rule::exists('communities', 'id')]
                : ['required'],
            'title' => ['required', 'string', 'max:255'],
            'market' => ['required', 'string', 'max:50'],
            'analysis_date' => ['required', 'date'],
            'market_overview' => ['nullable', 'string'],
            'trend_strength' => ['nullable', 'string', Rule::in(['weak', 'medium', 'strong', 'strong_up'])],
            'trend_structure' => ['nullable', 'string', Rule::in(['uptrend', 'downtrend', 'ranging'])],
            'key_zones' => ['nullable', 'string'],
            'entry_zones_description' => ['nullable', 'string'],
            'analyst_view' => ['nullable', 'string'],
            'strategy' => ['nullable', 'string'],
            'trading_plan' => ['nullable', 'string'],
            'chart_signals' => ['nullable', 'string'],
            'rsi_level' => ['nullable', 'string', 'max:255'],
            'order_block' => ['nullable', 'string', 'max:255'],
            'outlook_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
        ]);
    }

    private function prepareMarketAnalysisData(Request $request, array $data, ?MarketAnalysis $analysis = null): array
    {
        $data['title'] = trim($data['title']);
        $data['market'] = strtoupper(trim($data['market']));
        $data['trend_strength'] = $this->resolveStrengthKey($data['trend_strength'] ?? null);

        $trendKey = $this->resolveTrendKey($data['trend_structure'] ?? null);
        $data['trend_structure'] = $trendKey ? $this->trendStructureNarrative($trendKey) : null;

        if (empty($data['market_overview']) && $trendKey && $data['trend_strength']) {
            $data['market_overview'] = $this->generateMarketOverview($trendKey, $data['trend_strength']);
        }

        if ($request->hasFile('outlook_image')) {
            $data['outlook_image'] = $this->uploadOutlookImage($request);
        } elseif ($analysis) {
            unset($data['outlook_image']);
        }

        return $data;
    }

    private function uploadOutlookImage(Request $request): string
    {
        $image = $request->file('outlook_image');
        $destination = public_path('upload/outlook');

        if (! File::exists($destination)) {
            File::makeDirectory($destination, 0755, true);
        }

        $imageName = uniqid('outlook_', true) . '.' . $image->getClientOriginalExtension();
        $image->move($destination, $imageName);

        return 'upload/outlook/' . $imageName;
    }

    private function selectedCommunityIds($target): array
    {
        if ($target === 'all') {
            return Community::where('status', 1)->pluck('id')->all();
        }

        $ids = collect((array) $target)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return Community::where('status', 1)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();
    }

    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('market', 'like', "%{$search}%")
                    ->orWhere('Outlook_Code', 'like', "%{$search}%")
                    ->orWhere('market_overview', 'like', "%{$search}%")
                    ->orWhere('analyst_view', 'like', "%{$search}%");
            });
        }

        if ($request->filled('market')) {
            $query->where('market', $request->input('market'));
        }

        if ($request->filled('community_id')) {
            $query->where('community_id', $request->input('community_id'));
        }
    }

    private function composeDiscordMessage(MarketAnalysis $analysis, Community $community): string
    {
        $lines = [
            "**{$analysis->title}**",
            "交易品种: {$analysis->market}",
            "社区: {$community->name}",
            "日期: " . optional($analysis->analysis_date)->format('Y-m-d'),
        ];

        if ($analysis->Outlook_Code) {
            $lines[] = "分析编号: {$analysis->Outlook_Code}";
        }

        $sections = [
            '市场概况' => $analysis->market_overview,
            '趋势与结构' => $analysis->trend_structure,
            '关键区间' => $analysis->key_zones,
            '进场区 / 风险区' => $analysis->entry_zones_description,
            '分析师观点' => $analysis->analyst_view,
            '策略 / 操作建议' => $analysis->strategy,
            '图表信号总结' => $analysis->chart_signals,
            '交易执行计划' => $analysis->trading_plan,
        ];

        foreach ($sections as $label => $content) {
            if ($content) {
                $lines[] = "\n**{$label}**\n{$content}";
            }
        }

        if ($analysis->rsi_level || $analysis->order_block) {
            $technical = [];

            if ($analysis->rsi_level) {
                $technical[] = "RSI: {$analysis->rsi_level}";
            }

            if ($analysis->order_block) {
                $technical[] = "Order Block / FVG: {$analysis->order_block}";
            }

            $lines[] = "\n**技术备注**\n" . implode("\n", $technical);
        }

        $lines[] = "\n风险提醒：请严格遵守交易计划、尊重失效条件，并控制好仓位风险。";

        return implode("\n", $lines);
    }

    private function generateMarketOverview(string $structure, string $strength): string
    {
        $structureText = [
            'uptrend' => '市场目前维持多头结构，高点与低点持续抬升，买盘仍然占据主导。只要关键支撑区没有被有效跌破，整体思路以顺势做多为主。',
            'downtrend' => '市场目前维持空头结构，高点不断下移，低点同步走低，卖压仍然占据主导。只要关键阻力区没有被有效突破，整体思路以顺势做空为主。',
            'ranging' => '市场目前处于震荡区间运行，价格在支撑与阻力之间来回测试，方向暂时不明确。操作上以区间边缘确认信号为主，等待突破后再跟随新方向。',
        ];

        $strengthText = [
            'weak' => '趋势强度偏弱，动能不足，容易出现假突破或反复震荡，需降低仓位并严格控制风险。',
            'medium' => '趋势强度中等，市场节奏仍可操作，但需要等待明确确认后再执行。',
            'strong' => '趋势强度偏强，动能延续概率较高，可优先关注顺势延续机会。',
        ];

        return trim(($structureText[$structure] ?? '') . ' ' . ($strengthText[$strength] ?? ''));
    }

    private function trendStructureNarrative(string $structure): string
    {
        return [
            'uptrend' => '多头结构：价格持续形成更高高点与更高低点，整体节奏偏向上行。优先观察价格回踩关键支撑区后的确认信号；若关键支撑被有效跌破，则原多头结构失效。',
            'downtrend' => '空头结构：价格持续形成更低高点与更低低点，整体节奏偏向下行。优先观察价格反弹至关键阻力区后的承压信号；若关键阻力被有效突破，则原空头结构失效。',
            'ranging' => '震荡结构：价格在明确支撑与阻力之间运行，方向暂时不明确。优先观察区间边缘的确认信号，或等待有效突破与回踩后再跟随新趋势。',
        ][$structure];
    }

    private function resolveTrendKey(?string $value): ?string
    {
        $value = strtolower(trim((string) $value));

        if (in_array($value, ['uptrend', 'downtrend', 'ranging'], true)) {
            return $value;
        }

        if (str_contains($value, 'uptrend') || str_contains($value, 'higher high') || str_contains($value, 'bullish') || str_contains($value, '多头')) {
            return 'uptrend';
        }

        if (str_contains($value, 'downtrend') || str_contains($value, 'lower high') || str_contains($value, 'bearish') || str_contains($value, '空头')) {
            return 'downtrend';
        }

        if (str_contains($value, 'ranging') || str_contains($value, 'range') || str_contains($value, 'consolidat') || str_contains($value, '震荡')) {
            return 'ranging';
        }

        return null;
    }

    private function resolveStrengthKey(?string $value): ?string
    {
        $value = strtolower(trim((string) $value));

        if ($value === '') {
            return null;
        }

        if ($value === 'strong_up') {
            return 'strong';
        }

        if (in_array($value, ['weak', 'medium', 'strong'], true)) {
            return $value;
        }

        if (str_contains($value, 'strong')) {
            return 'strong';
        }

        if (str_contains($value, 'weak')) {
            return 'weak';
        }

        return 'medium';
    }

    private function generateUniqueOutlookCode(): string
    {
        do {
            $code = chr(random_int(65, 90)) . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (MarketAnalysis::where('Outlook_Code', $code)->exists());

        return $code;
    }

    private function breadcrumbs(array $items): array
    {
        return array_merge([
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
        ], $items);
    }

    private function ensureMarketManager(): void
    {
        abort_unless(auth()->check() && in_array((int) auth()->user()->role_id, [1, 2, 501], true), 403);
    }

    private function ensureTradingReader(): void
    {
        abort_unless(auth()->check() && in_array((int) auth()->user()->role_id, [1, 2, 501, 750, 760, 770], true), 403);
    }
}
