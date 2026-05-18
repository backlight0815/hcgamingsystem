<?php
namespace App\Http\Controllers\Trading;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\TradingPair;
use App\Models\TradingJournal;
use App\Models\SignalPerformance;
use App\Models\SignalPerformanceBackup;
use App\Models\Community; 
use App\Models\CommunityTpSetting; 
use App\Services\AppNotificationService;

use App\Services\DiscordService; // ✅ Add this
        // ✅ correct place
use App\Models\TradingSignal;     // ✅ correct 
use App\Models\TradingSignalBackup;
use App\Models\TradingSignalDiscord; // if using a separate table
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\FeatureToggle;
use Illuminate\Support\Facades\Validator;
use App\Models\TradingReason;
use App\Models\User;


class TradingSignalController extends Controller


{
    private const INSIGHT_LOT_SIZE = 0.01;
    private const USD_PER_PIP_PER_LOT = 10;

    private function canManageAllSignals(): bool
    {
        $user = Auth::user();

        return $user && in_array((int) $user->role_id, [1, 2, 999], true);
    }

    private function signalQueryForCurrentUser(array $with = [])
    {
        $query = TradingSignal::query();

        if (!empty($with)) {
            $query->with($with);
        }

        if (!$this->canManageAllSignals()) {
            $query->where('user_id', Auth::id());
        }

        return $query;
    }

    private function normalizePairSymbol(?string $pair): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) $pair));
    }

    private function parseSignalPrice($value): float
    {
        $value = str_replace(',', '', (string) $value);

        if (preg_match('/-?\d+(?:\.\d+)?/', $value, $matches)) {
            return (float) $matches[0];
        }

        return 0.0;
    }

    private function resolveSignalPipFactor(?string $pair): float
    {
        $normalizedPair = $this->normalizePairSymbol($pair);
        static $tradingPairs = null;

        if ($tradingPairs === null) {
            $tradingPairs = TradingPair::all(['symbol', 'pip_factor']);
        }

        $tradingPair = $tradingPairs->first(function ($tradingPair) use ($normalizedPair) {
            return $this->normalizePairSymbol($tradingPair->symbol) === $normalizedPair;
        });

        if ($tradingPair && (float) $tradingPair->pip_factor > 0) {
            return (float) $tradingPair->pip_factor;
        }

        if (strpos($normalizedPair, 'XAU') !== false || strpos($normalizedPair, 'GOLD') !== false) {
            return 0.1;
        }

        if (strpos($normalizedPair, 'JPY') !== false) {
            return 0.01;
        }

        return 0.0001;
    }

    private function calculateProfitPips(?string $pair, float $entryPrice, float $targetPrice): float
    {
        return round(abs($targetPrice - $entryPrice) / $this->resolveSignalPipFactor($pair), 1);
    }

    private function calculateInsightUsd(float $profitPips): float
    {
        return round($profitPips * self::INSIGHT_LOT_SIZE * self::USD_PER_PIP_PER_LOT, 2);
    }

    private function formatUsd(float $amount): string
    {
        $prefix = $amount < 0 ? '-' : '';

        return $prefix . '$' . number_format(abs($amount), 2, '.', ',');
    }

    private function formatTargetInsight(?string $pair, $entryPrice, $targetPrice): string
    {
        $entryPrice = $this->parseSignalPrice($entryPrice);
        $targetPrice = $this->parseSignalPrice($targetPrice);

        if ($entryPrice <= 0 || $targetPrice <= 0) {
            return '';
        }

        $profitPips = $this->calculateProfitPips($pair, $entryPrice, $targetPrice);
        $profitUsd = $this->calculateInsightUsd($profitPips);

        return ' | 0.01 lot est. ' . $this->formatUsd($profitUsd);
    }

    private function formatTpInsightLine(float $profitUsd): string
    {
        return 'USD Insight (0.01 lot): ' . $this->formatUsd($profitUsd) . "\n";
    }

    /**
     * Show all trading signals
     */
public function index(Request $request)
{
    $statusLabels = [
        0  => 'Pending',
        1  => 'Active',
        2  => 'TP1',
        3  => 'TP2',
        4  => 'TP3',
        5  => 'TP4',
        6  => 'TP5',
        7  => 'TP6',
        8  => 'TP7',
        9  => 'TP8',
        10 => 'TP9',
        11 => 'TP10',
        12 => 'Cancelled',
        13 => 'SL',
        14 => 'Done',
    ];

    $breadcrumbData = [
        ['label' => 'Trading Signals', 'url' => route('all.trading.signals')]
    ];
    $currentUser = auth()->user();
    $canManageAll = $this->canManageAllSignals();

    // Base Query
    $query = TradingSignal::with(['user', 'community']);

    // Role filter for non-admins
    if (!$canManageAll) {
        $query->where('user_id', $currentUser->id);
    }

    // ================= DATE FILTER =================
    if ($request->filled('from_date') && $request->filled('to_date')) {
        $from = Carbon::parse($request->from_date)->startOfDay();
        $to   = Carbon::parse($request->to_date)->endOfDay();
        $query->whereBetween('created_at', [$from, $to]);
    } elseif ($request->filled('quick_range')) {
        switch ($request->quick_range) {
            case 'today':
                $query->whereDate('created_at', Carbon::today());
                break;
            case '7days':
                $query->where('created_at', '>=', Carbon::now()->subDays(6)->startOfDay());
                break;
            case '30days':
                $query->where('created_at', '>=', Carbon::now()->subDays(29)->startOfDay());
                break;
            case '90days':
                $query->where('created_at', '>=', Carbon::now()->subDays(89)->startOfDay());
                break;
        }
    }

    if ($canManageAll && $request->filled('provider_id')) {
        $query->where('user_id', $request->provider_id);
    }

    if ($request->filled('trading_pair')) {
        $query->where('trading_pair', 'like', '%' . $request->trading_pair . '%');
    }

    if ($request->filled('status_filter')) {
        match ($request->status_filter) {
            'tp' => $query->whereBetween('status', [2, 11]),
            'be' => $query->where(function ($q) {
                $q->where('IsBE', 1)->orWhere('status', 15);
            }),
            'done' => $query->where(function ($q) {
                $q->where('IsDone', 1)->orWhere('status', 14);
            }),
            default => is_numeric($request->status_filter)
                ? $query->where('status', (int) $request->status_filter)
                : null,
        };
    }

    // Fetch signals after all filters
    $signals = $query->latest()->get();

    $totalSignals = $signals->count();
    $totalTP = $signals->whereBetween('status', [2, 11])->count();
    $totalSL = $signals->where('status', 13)->count();
    $totalCancel = $signals->where('status', 12)->count();
    $totalBE = $signals->filter(fn ($signal) => (int) $signal->IsBE === 1 || (int) $signal->status === 15)->count();
    $totalDone = $signals->filter(fn ($signal) => (int) $signal->IsDone === 1 || (int) $signal->status === 14)->count();
    $totalPending = $signals->where('status', 0)->count();
    $totalActive = $signals->where('status', 1)->count();
    $providers = User::whereIn('role_id', [201, 202])
        ->orderBy('username')
        ->get();

    return view('admin.trading_signal.signals_all', [
        'signals'       => $signals,
        'statusLabels'  => $statusLabels,
        'breadcrumbData'=> $breadcrumbData,
        'totalSignals'  => $totalSignals,
        'totalTP'       => $totalTP,
        'totalSL'       => $totalSL,
        'totalCancel'   => $totalCancel,
        'totalBE'       => $totalBE,
        'totalDone'     => $totalDone,
        'totalPending'  => $totalPending,
        'totalActive'   => $totalActive,
        'providers'     => $providers,
        'canManageAll'  => $canManageAll,
        'from_date'     => $request->from_date,
        'to_date'       => $request->to_date,
        'quick_range'   => $request->quick_range,
        'provider_id'   => $request->provider_id,
        'trading_pair'  => $request->trading_pair,
        'status_filter' => $request->status_filter,
    ]);
}



    /**
     * Show create form
     */
    public function create()
    {
        // Get all trading reasons
    $reasons = TradingReason::all();
            $communities = Community::all();
            
    // NEW: extract unique categories
    $categories = Community::select('category')->distinct()->pluck('category');

    return view('admin.trading_signal.signals_add', compact('communities','categories','reasons'));
    }

    /**
     * Store new trading signal (Blog-style)
     */

public function store(Request $request)
{
    // ------------------------------------
    // Validation
    // ------------------------------------
    $data = $request->validate([
        'trading_pair'      => 'required|string|max:255',
        'immediate_action'  => 'required|string|max:255',
        'entry_price'       => 'required|string|max:255',
        'stop_loss'         => 'required|string|max:255',
        'target_1'          => 'required|string|max:255',
        'target_2'          => 'required|string|max:255',
        'target_3'          => 'nullable|string|max:255',
        'target_4'          => 'nullable|string|max:255',
        'target_5'          => 'nullable|string|max:255',
        'target_6'          => 'nullable|string|max:255',
        'target_7'          => 'nullable|string|max:255',
        'target_8'          => 'nullable|string|max:255',
        'target_9'          => 'nullable|string|max:255',
        'target_10'         => 'nullable|string|max:255',
        'disclaimer'        => 'nullable|string',
        'risk_level'        => 'nullable|string|max:50',
        'signal_image'      => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
        'trading_reasons'   => 'nullable|array',
        'trading_reasons.*' => 'exists:trading_reason,id',
        'trigger_time'      => 'nullable|date',
        'link'              => 'nullable|string|max:255',
    ]);

    // ------------------------------------
    // Generate Unique Signal Code
    // ------------------------------------
    do {
        $signalCode = strtoupper(chr(rand(65, 90)) . rand(1000, 9999));
    } while (TradingSignal::where('signal_code', $signalCode)->exists());

    // ------------------------------------
    // Image Upload
    // ------------------------------------
    $imagePath = null;
    if ($request->hasFile('signal_image')) {
        $image = $request->file('signal_image');
        $imageName = time() . '_' . str_replace(' ', '_', $image->getClientOriginalName());
        $image->move(public_path('upload/signals'), $imageName);
        $imagePath = 'upload/signals/' . $imageName;
    }

    // ------------------------------------
    // Create Trading Signal (ONCE)
    // ------------------------------------
    $signal = TradingSignal::create(array_merge($data, [
        'signal_code'     => $signalCode,
        'signal_image'    => $imagePath,
        'user_id'         => auth()->id(),
        'trading_reasons' => $data['trading_reasons'] ?? [],
        'trigger_time'    => $data['trigger_time'] ?? null,
    ]));

    // ------------------------------------
    // Backup Table
    // ------------------------------------
    TradingSignalBackup::create(array_merge($data, [
        'signal_code'     => $signalCode,
        'signal_image'    => $imagePath,
        'user_id'         => auth()->id(),
        'trading_reasons' => $data['trading_reasons'] ?? [],
        'trigger_time'    => $data['trigger_time'] ?? null,
    ]));

    // ------------------------------------
    // Build Discord Message
    // ------------------------------------
    $message =
        "🆔 信号编号：{$signalCode}\n" .
        "交易品种：{$data['trading_pair']}\n" .
        "📊 风险等级：{$data['risk_level']}\n" .
        "📌 即时操作：{$data['immediate_action']} {$data['entry_price']}\n" .
        "⚙ 技术防守区：{$data['stop_loss']}\n" .
        "🎯 目标区：\n";

    for ($i = 1; $i <= 10; $i++) {
        $key = "target_$i";
        if (!empty($data[$key])) {
            $insight = $this->formatTargetInsight($data['trading_pair'], $data['entry_price'], $data[$key]);
            $message .= "TP{$i}: {$data[$key]}{$insight}\n";
        }
    }

    // Trading Reasons
    if (!empty($data['trading_reasons'])) {
        $reasonNames = TradingReason::whereIn('id', $data['trading_reasons'])
            ->pluck('name')
            ->toArray();
        $message .= "\n💡 原因: " . implode(', ', $reasonNames) . "\n";
    }

    // TradingView Link
    if (!empty($data['link'])) {
        $message .= "\n🔗 Link Access: {$data['link']}\n";
    }

    // Disclaimer
    $message .= "\n⚠ 免责声明: 所有信号仅供学习参考，请自行判断并管理风险。\n";
    $message .= "---------------------------------- 分割线 ----------------------------------\n";

    // ------------------------------------
    // Target Communities
    // ------------------------------------
    $communityTarget   = $request->input('community_target', 'all');
    $communityCategory = $request->input('community_category', 'all');

    $query = Community::where('status', 1);

    if ($communityTarget !== 'all') {
        $query->whereIn('id', (array) $communityTarget);
    }

    if (strtolower($communityCategory) !== 'all') {
        $query->where('category', $communityCategory);
    }

    $communities = $query->get();

    // ------------------------------------
    // Discord Integration
    // ------------------------------------
    if (feature_enabled('DiscordIntegration')) {

        foreach ($communities as $community) {

            if (!$community->discord_webhook) continue;

            $fileFullPath = $imagePath ? public_path($imagePath) : null;

            $discordMessage = '';
            if (
                feature_enabled('DiscordIntegration_Everyone') &&
                $community->discord_everyone_enabled
            ) {
                $discordMessage .= "@everyone\n\n";
            }

            $discordMessage .= $message;

            $discordData = DiscordService::send(
                $discordMessage,
                $community->discord_webhook,
                $fileFullPath
            );

            if ($discordData && isset($discordData['message_id'], $discordData['channel_id'])) {

                TradingSignalDiscord::create([
                    'trading_signal_id' => $signal->id,
                    'community_id'      => $community->id,
                    'category'          => $community->category, // cache OK
                    'message_id'        => $discordData['message_id'],
                    'channel_id'        => $discordData['channel_id'],
                ]);

            } else {
                \Log::error("Failed to send Discord message: {$community->name}");
            }
        }
    }

    return redirect()->route('all.trading.signals')->with([
        'message' => feature_enabled('DiscordIntegration')
            ? 'Trading Signal Added + Sent to Discord!'
            : 'Trading Signal Added (Discord disabled).',
        'alert-type' => 'success',
    ]);
}


    /**
     * Show edit form
     */
public function edit($id)
{
    $signal = $this->signalQueryForCurrentUser()->findOrFail($id);
    $reasons = TradingReason::all(); // get all reasons for multi-select

    return view('admin.trading_signal.signals_edit', compact('signal', 'reasons'));
}

    /**
     * Update trading signal (Blog-style)
     */
public function update(Request $request, $id)
{
    // 1️⃣ Validation
    $validator = Validator::make($request->all(), [
        'trading_pair'     => 'required|string|max:255',
        'signal_title'     => 'nullable|string|max:255',
        'immediate_action' => 'required|string|max:255',
        'entry_price'      => 'required|string|max:255',
        'stop_loss'        => 'required|string|max:255',
        'target_1'         => 'required|string|max:255',
        'target_2'         => 'required|string|max:255',
        'target_3'         => 'nullable|string|max:255',
        'target_4'         => 'nullable|string|max:255',
        'target_5'         => 'nullable|string|max:255',
        'target_6'         => 'nullable|string|max:255',
        'target_7'         => 'nullable|string|max:255',
        'target_8'         => 'nullable|string|max:255',
        'target_9'         => 'nullable|string|max:255',
        'target_10'        => 'nullable|string|max:255',
        'link'             => 'nullable|string|max:255',
        'disclaimer'       => 'nullable|string',
        'risk_level'       => 'nullable|string|max:50',
        'trading_reasons'  => 'nullable|array',
        'trading_reasons.*'=> 'exists:trading_reason,id',
    ]);

    if ($validator->fails()) {
        return redirect()->back()->withErrors($validator)->withInput();
    }

    // 2️⃣ Fetch signal with Discord messages
    $signal = $this->signalQueryForCurrentUser(['discordMessages'])->findOrFail($id);

    // 3️⃣ Update the trading signal in DB
    $signal->update([
        'trading_pair'     => $request->trading_pair,
        'signal_title'     => $request->signal_title,
        'immediate_action' => $request->immediate_action,
        'entry_price'      => $request->entry_price,
        'stop_loss'        => $request->stop_loss,
        'target_1'         => $request->target_1,
        'target_2'         => $request->target_2,
        'target_3'         => $request->target_3,
        'target_4'         => $request->target_4,
        'target_5'         => $request->target_5,
        'target_6'         => $request->target_6,
        'target_7'         => $request->target_7,
        'target_8'         => $request->target_8,
        'target_9'         => $request->target_9,
        'target_10'        => $request->target_10,
        'link'             => $request->link,
        'disclaimer'       => $request->disclaimer,
        'risk_level'       => $request->risk_level,
        'trading_reasons'  => $request->input('trading_reasons', []),
        'updated_at'       => now(),
    ]);

    // 4️⃣ Build the message content
    $discordMessage = "@everyone 📢 Trading Signal Updated!\n\n";
    $discordMessage .= "🆔 信号编号：{$signal->signal_code}\n";
    $discordMessage .= "交易品种：{$signal->trading_pair}\n";
    $discordMessage .= "即时操作：{$signal->immediate_action}  {$signal->entry_price}\n";
    $discordMessage .= "⚙ 技术防守区（Stop Loss）：{$signal->stop_loss}\n";
    $discordMessage .= "🎯 目标区：\n";

    for ($i = 1; $i <= 10; $i++) {
        $key = "target_$i";
        if (!empty($signal->$key)) {
            $insight = $this->formatTargetInsight($signal->trading_pair, $signal->entry_price, $signal->$key);
            $discordMessage .= "TP{$i}: {$signal->$key}{$insight}\n";
        }
    }

    if (!empty($signal->trading_reasons)) {
        $reasonNames = TradingReason::whereIn('id', $signal->trading_reasons)
            ->pluck('name')->toArray();
        if (!empty($reasonNames)) {
            $discordMessage .= "\n💡 原因: " . implode(', ', $reasonNames) . "\n";
        }
    }

    $discordMessage .= "\n⚠ 免责声明: 所有信号仅供学习参考。\n";
    $discordMessage .= "---------------------------------- 分割线 ----------------------------------\n";

    // 5️⃣ Feature toggle
    if (!feature_enabled('DiscordIntegration')) {
        return redirect()->route('all.trading.signals')->with([
            'message' => 'Trading Signal Updated (Discord disabled).',
            'alert-type' => 'success',
        ]);
    }

    $sentCount = 0;
    $skippedCount = 0;

    // 6️⃣ Update existing Discord messages (PATCH)
    foreach ($signal->discordMessages as $msg) {
        $community = Community::find($msg->community_id);
        if (!$community || !$community->discord_webhook || empty($msg->message_id)) {
            $skippedCount++;
            continue;
        }

        try {
            Http::patch("{$community->discord_webhook}/messages/{$msg->message_id}", [
                'content' => $discordMessage,
                'allowed_mentions' => ['parse' => ['everyone']],
            ]);
            $msg->touch(); // Update local timestamp
            $sentCount++;
        } catch (\Exception $e) {
            \Log::error("Discord update failed for {$community->name}: {$e->getMessage()}");
            $skippedCount++;
        }
    }

    // 7️⃣ Send NEW announcement message
    $newAnnouncement = "@everyone 🚨 Trading Signal Updated!\n\n";
    $newAnnouncement .= "🆔 信号编号：{$signal->signal_code}\n";
    $newAnnouncement .= "**{$signal->trading_pair}** 已更新，请尽快查看最新操作。\n";
    $newAnnouncement .= "👉 即时操作：{$signal->immediate_action}  {$signal->entry_price}\n";
    $newAnnouncement .= "👉 技术防守区：{$signal->stop_loss}\n";
    $newAnnouncement .= "📌 请参考更新后的 TP、SL 与内容。\n";
    $newAnnouncement .= "---------------------------------- 分割线 ----------------------------------\n";

    foreach ($signal->discordMessages as $msg) {
        $community = Community::find($msg->community_id);
        if (!$community || !$community->discord_webhook) continue;

        try {
            Http::post($community->discord_webhook, [
                'content' => $newAnnouncement,
                'allowed_mentions' => ['parse' => ['everyone']],
            ]);
        } catch (\Exception $e) {
            \Log::error("Discord new announcement failed for {$community->name}: {$e->getMessage()}");
        }
    }

    return redirect()->route('all.trading.signals')->with([
        'message' => "Trading Signal Updated Successfully. Original messages updated: {$sentCount}, Skipped: {$skippedCount}. New announcement sent.",
        'alert-type' => 'success',
    ]);
}



public function cancel(Request $request, $id)
{
    $signal = $this->signalQueryForCurrentUser(['discordMessages'])->findOrFail($id);

    // Only cancel if not already cancelled
    if ($signal->status == 12) {
        return redirect()->back()->with([
            'message' => 'This signal is already cancelled.',
            'alert-type' => 'info'
        ]);
    }

    $discordEnabled = feature_enabled('DiscordIntegration');

    try {
        // Reason from input
        $reason = $request->input('reason') ?? 'No reason provided';

        // Build Discord message
        $cancelMessage = "@everyone ⚠️ 该交易信号已取消。\n"
                       . "🆔 信号编号：{$signal->signal_code}\n"
                       . "交易品种：{$signal->trading_pair}\n"
                       . "原即时操作：{$signal->immediate_action} {$signal->entry_price}\n"
                       . "取消原因：{$reason}\n"
                       . "请根据策略进行操作\n"
                       . "---------------------------------- 分割线 ----------------------------------\n";

        $sentCount = 0;
        $skippedCount = 0;

        if ($discordEnabled && $signal->discordMessages->count() > 0) {

            foreach ($signal->discordMessages as $msg) {

                // Get community
                $community = Community::find($msg->community_id);
                if (!$community || !$community->discord_webhook) {
                    $skippedCount++;
                    continue;
                }

                // Check if cancel is enabled in community
                $cancelEnabled = $community->cancelSetting?->enabled ?? 1;
                if (!$cancelEnabled) {
                    $skippedCount++;
                    continue;
                }

                // Send Discord message
                \Illuminate\Support\Facades\Http::post($community->discord_webhook . "?wait=true", [
                    'content' => $cancelMessage,
                    'allowed_mentions' => ['parse' => ['everyone']],
                ]);

                $sentCount++;
            }
        }

        // Update main signal
        $signal->update([
            'status' => 12,
            'cancel_reason' => $reason,
            'updated_at' => now(),
        ]);

        // Update backup signal
        TradingSignalBackup::where('signal_code', $signal->signal_code)
            ->update([
                'status' => 12,
                'cancel_reason' => $reason,
                'updated_at' => now(),
            ]);

    } catch (\Exception $e) {
        \Log::error('Cancel signal failed: ' . $e->getMessage());

        return redirect()->back()->with([
            'message' => 'Failed to cancel signal: ' . $e->getMessage(),
            'alert-type' => 'error'
        ]);
    }

    $discordStatus = $discordEnabled ? "Discord message sent." : "Discord integration is disabled.";

    return redirect()->back()->with([
        'message' => "Trading Signal Cancelled Successfully. {$discordStatus} Sent: {$sentCount}, Skipped: {$skippedCount}.",
        'alert-type' => 'success'
    ]);
}

public function activate(Request $request, $id)
{
    $signal = $this->signalQueryForCurrentUser(['discordMessages'])->findOrFail($id);

    // Already active
    if ($signal->status == 1) {
        return redirect()->back()->with([
            'message' => 'This signal is already active.',
            'alert-type' => 'info'
        ]);
    }

    $discordEnabled = feature_enabled('DiscordIntegration');

    try {

        // ------------------------------------
        // Handle trigger time (optional)
        // ------------------------------------
        $triggerTimeInput = $request->input('trigger_time');
        $timezone         = 'Asia/Kuala_Lumpur';
        $triggerTimeInTz  = null;

        if ($triggerTimeInput) {
            $triggerTimeInTz = Carbon::parse(
                $triggerTimeInput,
                $timezone
            )->toDateTimeString();
        }

        // ------------------------------------
        // Update Trading Signal
        // ------------------------------------
        $signal->update([
            'status'       => 1,
            'trigger_time' => $triggerTimeInTz,
            'updated_at'   => now(),
        ]);

        // Backup sync
        TradingSignalBackup::where('signal_code', $signal->signal_code)
            ->update([
                'status'       => 1,
                'trigger_time' => $triggerTimeInTz,
                'updated_at'   => now(),
            ]);

        // ------------------------------------
        // Build Discord activation message
        // ------------------------------------
        $activateMessage  = "@everyone ✅ 该交易信号已激活。\n\n";
        $activateMessage .= "🆔 信号编号：{$signal->signal_code}\n";
        $activateMessage .= "交易品种：{$signal->trading_pair}\n";
        $activateMessage .= "即时操作：{$signal->immediate_action} {$signal->entry_price}\n";

        if ($triggerTimeInTz) {
            $activateMessage .= "触发时间：{$triggerTimeInTz}\n";
        }

        $activateMessage .= "请根据策略进行操作\n";
        $activateMessage .= "---------------------------------- 分割线 ----------------------------------\n";

        // ------------------------------------
        // Send to Discord (per community)
        // ------------------------------------
        if ($discordEnabled) {

            foreach ($signal->discordMessages as $discordMsg) {

                // Get community via FK (NO name lookup)
                $community = Community::find($discordMsg->community_id);

                if (!$community || !$community->discord_webhook) {
                    continue;
                }

                Http::post(
                    $community->discord_webhook . '?wait=true',
                    [
                        'content' => $activateMessage,
                        'allowed_mentions' => [
                            'parse' => ['everyone']
                        ],
                    ]
                );
            }
        }

    } catch (\Exception $e) {

        \Log::error('Activate signal failed', [
            'signal_id' => $signal->id,
            'error'     => $e->getMessage(),
        ]);

        return redirect()->back()->with([
            'message' => 'Failed to activate signal.',
            'alert-type' => 'error'
        ]);
    }

    AppNotificationService::notifyRoles(
        [750, 760, 770],
        'Trading signal activated',
        $signal->trading_pair . ' signal ' . $signal->signal_code . ' is now active.',
        route('member.signals.view', $signal->id),
        'trading_signal'
    );

    return redirect()->back()->with([
        'message' => $discordEnabled
            ? 'Trading Signal Activated Successfully. Discord message sent.'
            : 'Trading Signal Activated Successfully. Discord disabled.',
        'alert-type' => 'success'
    ]);
}


public function breakeven($id)
{
    $signal = $this->signalQueryForCurrentUser()->findOrFail($id);

    // Only set to Breakeven if not already BE
    if ($signal->status == 15) { // 15 = Breakeven
        return redirect()->back()->with([
            'message' => 'This signal is already at Breakeven.',
            'alert-type' => 'info'
        ]);
    }

    // Check Feature Toggle
    $discordEnabled = feature_enabled('DiscordIntegration');

    try {
        $beMessage = "@everyone ⚖️ 该交易信号已设置为保本 (Breakeven)。\n\n"
                   . "🆔 信号编号：{$signal->signal_code}\n"
                   . "交易品种：{$signal->trading_pair}\n"
                   . "即时操作：{$signal->immediate_action} {$signal->entry_price}\n"
                   . "请根据策略进行操作\n"
                   . "---------------------------------- 分割线 ----------------------------------\n";

        // Send Discord message only if feature is enabled
        if ($discordEnabled) {
            foreach ($signal->discordMessages as $msg) {
                $community = Community::where('name', $msg->community)->first();

                if ($community && $community->discord_webhook) {
                    \Illuminate\Support\Facades\Http::post($community->discord_webhook . "?wait=true", [
                        'content' => $beMessage,
                        'allowed_mentions' => ['parse' => ['everyone']],
                    ]);
                }
            }
        }

        // Update DB status to Breakeven
        $signal->update([
            'status' => 15, // Breakeven
            'updated_at' => now(),
        ]);

    } catch (\Exception $e) {
        \Log::error('Set Breakeven failed: ' . $e->getMessage());
        return redirect()->back()->with([
            'message' => 'Failed to set Breakeven: ' . $e->getMessage(),
            'alert-type' => 'error'
        ]);
    }

    // Return message indicating Discord status
    $discordStatus = $discordEnabled ? 'Discord message sent.' : 'Discord integration is disabled.';
    return redirect()->back()->with([
        'message' => "Trading Signal set to Breakeven successfully. {$discordStatus}",
        'alert-type' => 'success'
    ]);
}
public function sl($id)
{
    $signal = $this->signalQueryForCurrentUser(['discordMessages'])->findOrFail($id);

    // Only allow SL if Active or TP1~TP10
    if (!in_array($signal->status, range(1, 10))) {
        return redirect()->back()->with([
            'message' => 'Only Active or TP signals can be marked as SL.',
            'alert-type' => 'info'
        ]);
    }

    $discordEnabled = feature_enabled('DiscordIntegration');

    try {
        // 1️⃣ Update main signal
        $signal->update([
            'status'     => 13, // SL
            'updated_at' => now(),
        ]);

        // 2️⃣ Get backup signal
        $backupSignal = TradingSignalBackup::where(
            'signal_code',
            $signal->signal_code
        )->first();

        if ($backupSignal) {
            $backupSignal->update([
                'status'     => 13,
                'updated_at' => now(),
            ]);
        }

        // 3️⃣ Calculate SL pips (negative)
        $entryPrice = $this->parseSignalPrice($signal->entry_price);
        $slPrice    = $this->parseSignalPrice($signal->stop_loss);

        $profitPips = ($entryPrice > 0 && $slPrice > 0)
            ? -1 * $this->calculateProfitPips($signal->trading_pair, $entryPrice, $slPrice)
            : 0;
        $profitUsd = $this->calculateInsightUsd($profitPips);
        $profitUsdText = $this->formatUsd($profitUsd);

        // 4️⃣ Save performance PER COMMUNITY
        foreach ($signal->discordMessages as $msg) {

            $community = Community::find($msg->community_id);
            if (!$community) continue;

      SignalPerformance::updateOrCreate(
    ['signal_id' => $signal->id],
    [
        'tp_hit'       => null,
        'is_sl'        => true,
        'is_tp'        => false,
        'is_be'        => false,
        'is_cancelled' => false,
        'profit_pips'  => $profitPips,
        'profit_usd'   => $profitUsd,
        'created_at'   => $signal->created_at,
        'updated_at'   => now(),
    ]
);

            // BACKUP performance (✅ FIXED)
      if ($backupSignal) {
    SignalPerformanceBackup::updateOrCreate(
        ['signal_id' => $backupSignal->id],
        [
            'tp_hit'       => null,
            'is_sl'        => true,
            'is_tp'        => false,
            'is_be'        => false,
            'is_cancelled' => false,
            'profit_pips'  => $profitPips,
            'profit_usd'   => $profitUsd,
            'created_at'   => $signal->created_at,
            'updated_at'   => now(),
        ]
    );
}

        }

        // 5️⃣ Discord message
        if ($discordEnabled) {

            $slMessage =
                "@everyone ⚠️ Trading Signal hit SL!\n\n" .
                "🆔 信号编号：{$signal->signal_code}\n" .
                "交易品种：{$signal->trading_pair}\n" .
                "入场价：{$signal->entry_price}\n" .
                "止损价：{$signal->stop_loss}\n" .
                "Profit Pips：{$profitPips}\n" .
                "---------------------------------- 分割线 ----------------------------------\n";

            foreach ($signal->discordMessages as $msg) {

                $community = Community::find($msg->community_id);
                if (!$community || !$community->discord_webhook) continue;

                \Illuminate\Support\Facades\Http::post(
                    $community->discord_webhook . '?wait=true',
                    [
                        'content' => $slMessage,
                        'allowed_mentions' => [
                            'parse' => ['everyone']
                        ],
                    ]
                );
            }
        }

    } catch (\Exception $e) {

        \Log::error('SL signal update failed', [
            'signal_id' => $signal->id,
            'error'     => $e->getMessage(),
        ]);

        return redirect()->back()->with([
            'message'    => 'Failed to mark SL: ' . $e->getMessage(),
            'alert-type' => 'error'
        ]);
    }

    return redirect()->back()->with([
        'message'    => "Trading Signal marked as SL successfully. Profit Pips: {$profitPips}. USD Insight (0.01 lot): {$profitUsdText}.",
        'alert-type' => 'success'
    ]);
}


public function markDone($id) {
    $signal = $this->signalQueryForCurrentUser()->findOrFail($id);
    $signal->IsDone = 1;
    $signal->is_done = 1;
    $signal->save();
  // ✅ Update backup table
        TradingSignalBackup::where('signal_code', $signal->signal_code)
            ->update([
                'IsDone'    => 1,
                'is_done'   => 1,
            ]);

    return redirect()->back()->with([
        'message' => "This trading signal has been successfully marked as completed.",
        'alert-type' => 'success'
    ]);
}

public function tp1($id)
{
    $signal = $this->signalQueryForCurrentUser(['discordMessages'])->findOrFail($id);

    // Only ACTIVE signals can hit TP1
    if ($signal->status != 1) {
        return redirect()->back()->with([
            'message' => 'Only active signals can be marked as TP1.',
            'alert-type' => 'info'
        ]);
    }

    $discordEnabled = feature_enabled('DiscordIntegration');

    try {
        // Calculate profit pips
        $entryPrice = $this->parseSignalPrice($signal->entry_price);
        $tpPrice    = $this->parseSignalPrice($signal->target_1);

        if ($entryPrice <= 0 || $tpPrice <= 0) {
            return redirect()->back()->with([
                'message' => 'Entry or TP1 price is invalid.',
                'alert-type' => 'error'
            ]);
        }

        $profitPips = $this->calculateProfitPips($signal->trading_pair, $entryPrice, $tpPrice);
        $profitUsd = $this->calculateInsightUsd($profitPips);
        $profitUsdText = $this->formatUsd($profitUsd);

        // ===============================
        // Update single SignalPerformance (based on signal_id only)
        // ===============================
        $performance = SignalPerformance::updateOrCreate(
            ['signal_id' => $signal->id],
            [
                'tp_hit' => 1,
                'is_sl' => false,
                'is_cancelled' => false,
                'profit_pips' => $profitPips,
                'profit_usd' => $profitUsd,
                'created_at' => $signal->created_at,
                'updated_at' => now(),
            ]
        );

        // ===============================
        // Update single SignalPerformanceBackup
        // ===============================
        $backupSignal = TradingSignalBackup::where('signal_code', $signal->signal_code)->first();
        if ($backupSignal) {
            SignalPerformanceBackup::updateOrCreate(
                ['signal_id' => $backupSignal->id],
                [
                    'tp_hit' => 1,
                    'is_sl' => false,
                    'is_cancelled' => false,
                    'profit_pips' => $profitPips,
                    'profit_usd' => $profitUsd,
                    'created_at' => $signal->created_at,
                    'updated_at' => now(),
                ]
            );
        }

        // ===============================
        // Update main signal status
        // ===============================
        $signal->update([
            'status' => 2, // TP1
            'updated_at' => now(),
        ]);

        if ($backupSignal) {
            $backupSignal->update([
                'status' => 2,
                'updated_at' => now(),
            ]);
        }

        // ===============================
        // Discord message
        // ===============================
        if ($discordEnabled) {
            $tpMessage  = "@everyone 🎯 Trading Signal reached TP1!\n\n";
            $tpMessage .= "🆔 信号编号：{$signal->signal_code}\n";
            $tpMessage .= "交易品种：{$signal->trading_pair}\n";
            $tpMessage .= "入场价：{$signal->entry_price}\n";
            $tpMessage .= "TP1：{$tpPrice}\n";
            $tpMessage .= "Profit Pips：{$profitPips}\n";
            $tpMessage .= $this->formatTpInsightLine($profitUsd);
            $tpMessage .= "---------------------------------- 分割线 ----------------------------------\n";

            foreach ($signal->discordMessages as $discordMsg) {
                $community = Community::find($discordMsg->community_id);
                if (!$community || !$community->discord_webhook) continue;

                $tpSetting = CommunityTpSetting::where('community_id', $community->id)->first();
                if (!$tpSetting || (int) $tpSetting->enabled  !== 1) continue;

                Http::post(
                    $community->discord_webhook . '?wait=true',
                    [
                        'content' => $tpMessage,
                        'allowed_mentions' => ['parse' => ['everyone']],
                    ]
                );
            }
        }

    } catch (\Exception $e) {
        \Log::error('TP1 signal update failed', [
            'signal_id' => $signal->id,
            'error' => $e->getMessage(),
        ]);

        return redirect()->back()->with([
            'message' => 'Failed to mark TP1.',
            'alert-type' => 'error'
        ]);
    }

    return redirect()->back()->with([
        'message' => "Trading Signal marked as TP1 successfully. Profit Pips: {$profitPips}. USD Insight (0.01 lot): {$profitUsdText}. "
            . ($discordEnabled ? 'Discord message sent.' : 'Discord disabled.'),
        'alert-type' => 'success'
    ]);
}


public function tp2($id)
{
    $signal = $this->signalQueryForCurrentUser(['discordMessages'])->findOrFail($id);

    // Only TP1 signals can hit TP2
    if ($signal->status != 2) {
        return redirect()->back()->with([
            'message' => 'Only signals marked as TP1 can be marked as TP2.',
            'alert-type' => 'info'
        ]);
    }

    $discordEnabled = feature_enabled('DiscordIntegration');
    $sentCount = 0;
    $skippedCount = 0;

    try {
        // Calculate profit pips
        $entryPrice = $this->parseSignalPrice($signal->entry_price);
        $tpPrice    = $this->parseSignalPrice($signal->target_2);

        if ($entryPrice <= 0 || $tpPrice <= 0) {
            return redirect()->back()->with([
                'message' => 'Entry or TP2 price is invalid.',
                'alert-type' => 'error'
            ]);
        }

        $profitPips = $this->calculateProfitPips($signal->trading_pair, $entryPrice, $tpPrice);
        $profitUsd = $this->calculateInsightUsd($profitPips);
        $profitUsdText = $this->formatUsd($profitUsd);

        // ===============================
        // Update single SignalPerformance (based on signal_id only)
        // ===============================
        $performance = SignalPerformance::updateOrCreate(
            ['signal_id' => $signal->id],
            [
                'tp_hit' => 2,
                'is_sl' => false,
                'is_cancelled' => false,
                'profit_pips' => $profitPips,
                'profit_usd' => $profitUsd,
                'created_at' => $signal->created_at,
                'updated_at' => now(),
            ]
        );

        // ===============================
        // Update single SignalPerformanceBackup
        // ===============================
        $backupSignal = TradingSignalBackup::where('signal_code', $signal->signal_code)->first();
        if ($backupSignal) {
            SignalPerformanceBackup::updateOrCreate(
                ['signal_id' => $backupSignal->id],
                [
                    'tp_hit' => 2,
                    'is_sl' => false,
                    'is_cancelled' => false,
                    'profit_pips' => $profitPips,
                    'profit_usd' => $profitUsd,
                    'created_at' => $signal->created_at,
                    'updated_at' => now(),
                ]
            );
        }

        // ===============================
        // Update main signal status
        // ===============================
        $signal->update([
            'status' => 3, // TP2 reached
            'updated_at' => now(),
        ]);

        if ($backupSignal) {
            $backupSignal->update([
                'status' => 3,
                'updated_at' => now(),
            ]);
        }

        // ===============================
        // Discord message
        // ===============================
        if ($discordEnabled) {
            $tpMessage  = "@everyone 🎯 Trading Signal reached TP2!\n\n";
            $tpMessage .= "🆔 信号编号：{$signal->signal_code}\n";
            $tpMessage .= "交易品种：{$signal->trading_pair}\n";
            $tpMessage .= "入场价：{$signal->entry_price}\n";
            $tpMessage .= "TP2：{$tpPrice}\n";
            $tpMessage .= "Profit Pips：{$profitPips}\n";
            $tpMessage .= $this->formatTpInsightLine($profitUsd);
            $tpMessage .= "---------------------------------- 分割线 ----------------------------------\n";


            foreach ($signal->discordMessages as $discordMsg) {
                $community = Community::find($discordMsg->community_id);
                if (!$community || !$community->discord_webhook) {
                    $skippedCount++;
                    continue;
                }

                $tpSetting = CommunityTpSetting::where('community_id', $community->id)->first();
                if (!$tpSetting || (int)$tpSetting->enabled !== 1) {
                    $skippedCount++;
                    continue;
                }

                Http::post(
                    $community->discord_webhook . '?wait=true',
                    [
                        'content' => $tpMessage,
                        'allowed_mentions' => ['parse' => ['everyone']],
                    ]
                );

                $sentCount++;
            }
        }

    } catch (\Exception $e) {
        \Log::error('TP2 signal update failed', [
            'signal_id' => $signal->id,
            'error' => $e->getMessage(),
        ]);

        return redirect()->back()->with([
            'message' => 'Failed to mark TP2.',
            'alert-type' => 'error'
        ]);
    }

    return redirect()->back()->with([
        'message' => "Trading Signal marked as TP2 successfully. Profit Pips: {$profitPips}. USD Insight (0.01 lot): {$profitUsdText}. "
            . "Sent: {$sentCount}, Skipped: {$skippedCount}.",
        'alert-type' => 'success'
    ]);
}
public function tp3($id)
{
    $signal = $this->signalQueryForCurrentUser(['discordMessages'])->findOrFail($id);

    // Only TP2 signals can hit TP3
    if ($signal->status != 3) {
        return redirect()->back()->with([
            'message' => 'Only signals marked as TP2 can be marked as TP3.',
            'alert-type' => 'info'
        ]);
    }

    $discordEnabled = feature_enabled('DiscordIntegration');

    try {
        // Validate prices
        $entryPrice = $this->parseSignalPrice($signal->entry_price);
        $tpPrice    = $this->parseSignalPrice($signal->target_3);

        if ($entryPrice <= 0 || $tpPrice <= 0) {
            return redirect()->back()->with([
                'message' => 'Entry or TP3 price is invalid.',
                'alert-type' => 'error'
            ]);
        }

        // Calculate profit pips (only once per signal)
        $profitPips = $this->calculateProfitPips($signal->trading_pair, $entryPrice, $tpPrice);
        $profitUsd = $this->calculateInsightUsd($profitPips);
        $profitUsdText = $this->formatUsd($profitUsd);

        // ===============================
        // Update single SignalPerformance
        // ===============================
        $performance = SignalPerformance::updateOrCreate(
            ['signal_id' => $signal->id],
            [
                'tp_hit' => 3,
                'is_sl' => false,
                'is_cancelled' => false,
                'profit_pips' => $profitPips,
                'profit_usd' => $profitUsd,
                'created_at' => $signal->created_at,
                'updated_at' => now(),
            ]
        );

        // ===============================
        // Update single SignalPerformanceBackup
        // ===============================
        $backupSignal = TradingSignalBackup::where('signal_code', $signal->signal_code)->first();
        if ($backupSignal) {
            SignalPerformanceBackup::updateOrCreate(
                ['signal_id' => $backupSignal->id],
                [
                    'tp_hit' => 3,
                    'is_sl' => false,
                    'is_cancelled' => false,
                    'profit_pips' => $profitPips,
                    'profit_usd' => $profitUsd,
                    'created_at' => $signal->created_at,
                    'updated_at' => now(),
                ]
            );
        }

        // ===============================
        // Update main signal status
        // ===============================
        $signal->update([
            'status' => 4, // TP3 reached
            'updated_at' => now(),
        ]);

        if ($backupSignal) {
            $backupSignal->update([
                'status' => 4,
                'updated_at' => now(),
            ]);
        }

        // ===============================
        // Discord message
        // ===============================
        $sentCount = 0;
        $skippedCount = 0;

        if ($discordEnabled) {
            $tpMessage  = "@everyone 🎯 Trading Signal reached TP3!\n\n";
            $tpMessage .= "🆔 信号编号：{$signal->signal_code}\n";
            $tpMessage .= "交易品种：{$signal->trading_pair}\n";
            $tpMessage .= "入场价：{$signal->entry_price}\n";
            $tpMessage .= "TP3：{$tpPrice}\n";
            $tpMessage .= "Profit Pips：{$profitPips}\n";
            $tpMessage .= $this->formatTpInsightLine($profitUsd);
            $tpMessage .= "---------------------------------- 分割线 ----------------------------------\n";

            foreach ($signal->discordMessages as $discordMsg) {
                $community = Community::find($discordMsg->community_id);
                if (!$community || !$community->discord_webhook) {
                    $skippedCount++;
                    continue;
                }

                $tpSetting = CommunityTpSetting::where('community_id', $community->id)->first();
                if (!$tpSetting || (int)$tpSetting->enabled !== 1) {
                    $skippedCount++;
                    continue;
                }

                Http::post(
                    $community->discord_webhook . '?wait=true',
                    [
                        'content' => $tpMessage,
                        'allowed_mentions' => ['parse' => ['everyone']],
                    ]
                );

                $sentCount++;
            }
        }

    } catch (\Exception $e) {
        \Log::error('TP3 signal update failed', [
            'signal_id' => $signal->id,
            'error' => $e->getMessage(),
        ]);

        return redirect()->back()->with([
            'message' => 'Failed to mark TP3.',
            'alert-type' => 'error'
        ]);
    }

    return redirect()->back()->with([
        'message' => "Trading Signal marked as TP3 successfully. Profit Pips: {$profitPips}. USD Insight (0.01 lot): {$profitUsdText}. "
            . "Sent: {$sentCount}, Skipped: {$skippedCount}.",
        'alert-type' => 'success'
    ]);
}
public function tp4($id)
{
    $signal = $this->signalQueryForCurrentUser(['discordMessages'])->findOrFail($id);

    // ✅ Only allow TP4 after TP3
    if ($signal->status != 4) {
        return redirect()->back()->with([
            'message' => 'Only signals marked as TP3 can be marked as TP4.',
            'alert-type' => 'info'
        ]);
    }

    $discordEnabled = feature_enabled('DiscordIntegration');
    $sentCount = 0;
    $skippedCount = 0;

    try {
        $entryPrice = $this->parseSignalPrice($signal->entry_price);
        $tpPrice    = $this->parseSignalPrice($signal->target_4);

        if ($entryPrice <= 0 || $tpPrice <= 0) {
            return redirect()->back()->with([
                'message' => 'Entry or TP4 price is invalid.',
                'alert-type' => 'error'
            ]);
        }

        // ✅ Calculate profit pips (once per signal)
        $profitPips = $this->calculateProfitPips($signal->trading_pair, $entryPrice, $tpPrice);
        $profitUsd = $this->calculateInsightUsd($profitPips);
        $profitUsdText = $this->formatUsd($profitUsd);

        // ===============================
        // Update SignalPerformance (single row)
        // ===============================
        $performance = SignalPerformance::updateOrCreate(
            ['signal_id' => $signal->id],
            [
                'tp_hit' => 4,
                'is_sl' => false,
                'is_cancelled' => false,
                'profit_pips' => $profitPips,
                'profit_usd' => $profitUsd,
                'created_at' => $signal->created_at,
                'updated_at' => now(),
            ]
        );

        // ===============================
        // Update SignalPerformanceBackup
        // ===============================
        $backupSignal = TradingSignalBackup::where('signal_code', $signal->signal_code)->first();
        if ($backupSignal) {
            SignalPerformanceBackup::updateOrCreate(
                ['signal_id' => $backupSignal->id],
                [
                    'tp_hit' => 4,
                    'is_sl' => false,
                    'is_cancelled' => false,
                    'profit_pips' => $profitPips,
                    'profit_usd' => $profitUsd,
                    'created_at' => $signal->created_at,
                    'updated_at' => now(),
                ]
            );
        }

        // ===============================
        // Update main signal status
        // ===============================
        $signal->update([
            'status' => 5, // TP4 reached
            'updated_at' => now(),
        ]);

        if ($backupSignal) {
            $backupSignal->update([
                'status' => 5,
                'updated_at' => now(),
            ]);
        }

        // ===============================
        // Discord message
        // ===============================
        if ($discordEnabled && $signal->discordMessages->count() > 0) {
            $tpMessage = "@everyone 🎯 Trading Signal reached TP4!\n\n"
                . "🆔 信号编号：{$signal->signal_code}\n"
                . "交易品种：{$signal->trading_pair}\n"
                . "入场价：{$signal->entry_price}\n"
                . "TP4：{$tpPrice}\n"
                . "Profit Pips：{$profitPips}\n"
                . $this->formatTpInsightLine($profitUsd)
                . "---------------------------------- 分割线 ----------------------------------\n";

            foreach ($signal->discordMessages as $msg) {
                $community = \App\Models\Community::find($msg->community_id);

                if (!$community || !$community->discord_webhook) {
                    $skippedCount++;
                    continue;
                }

                // 🔒 STRICT TP4 SETTING CHECK
                $tpSetting = $community->tpSettings()
                    ->where('tp_level', 4)
                    ->where('enabled', 1)
                    ->first();

                if (!$tpSetting) {
                    $skippedCount++;
                    continue;
                }

                try {
                    \Illuminate\Support\Facades\Http::post(
                        $community->discord_webhook . '?wait=true',
                        [
                            'content' => $tpMessage,
                            'allowed_mentions' => ['parse' => ['everyone']],
                        ]
                    );
                    $sentCount++;
                } catch (\Exception $e) {
                    \Log::error("Discord TP4 failed for {$community->name}: " . $e->getMessage());
                }
            }
        }

    } catch (\Exception $e) {
        \Log::error('TP4 signal update failed: ' . $e->getMessage());

        return redirect()->back()->with([
            'message' => 'Failed to mark TP4: ' . $e->getMessage(),
            'alert-type' => 'error'
        ]);
    }

    return redirect()->back()->with([
        'message' => "TP4 updated successfully. Profit Pips: {$profitPips}. USD Insight (0.01 lot): {$profitUsdText}. Sent: {$sentCount}, Skipped: {$skippedCount}.",
        'alert-type' => 'success'
    ]);
}


public function tp5($id)
{
    $signal = $this->signalQueryForCurrentUser(['discordMessages'])->findOrFail($id);

    // ✅ Only allow TP5 after TP4
    if ($signal->status != 5) {
        return redirect()->back()->with([
            'message' => 'Only signals marked as TP4 can be marked as TP5.',
            'alert-type' => 'info'
        ]);
    }

    $discordEnabled = feature_enabled('DiscordIntegration');
    $sentCount = 0;
    $skippedCount = 0;

    try {
        // 🔹 Validate prices
        $entryPrice = $this->parseSignalPrice($signal->entry_price);
        $tpPrice    = $this->parseSignalPrice($signal->target_5);

        if ($entryPrice <= 0 || $tpPrice <= 0) {
            return redirect()->back()->with([
                'message' => 'Entry or TP5 price is invalid.',
                'alert-type' => 'error'
            ]);
        }

        // 🔹 Calculate profit pips (once per signal code)
        $profitPips = $this->calculateProfitPips($signal->trading_pair, $entryPrice, $tpPrice);
        $profitUsd = $this->calculateInsightUsd($profitPips);
        $profitUsdText = $this->formatUsd($profitUsd);

        // ===============================
        // SignalPerformance (single row)
        // ===============================
        $performance = SignalPerformance::updateOrCreate(
            ['signal_id' => $signal->id],
            [
                'tp_hit' => 5,
                'is_sl' => false,
                'is_cancelled' => false,
                'profit_pips' => $profitPips,
                'profit_usd' => $profitUsd,
                'created_at' => $signal->created_at,
                'updated_at' => now(),
            ]
        );

        // ===============================
        // Backup Performance
        // ===============================
        $backupSignal = TradingSignalBackup::where('signal_code', $signal->signal_code)->first();
        if ($backupSignal) {
            SignalPerformanceBackup::updateOrCreate(
                ['signal_id' => $backupSignal->id],
                [
                    'tp_hit' => 5,
                    'is_sl' => false,
                    'is_cancelled' => false,
                    'profit_pips' => $profitPips,
                    'profit_usd' => $profitUsd,
                    'created_at' => $signal->created_at,
                    'updated_at' => now(),
                ]
            );
        }

        // ===============================
        // Update main signal status
        // ===============================
        $signal->update([
            'status' => 6, // TP5 reached
            'updated_at' => now(),
        ]);

        if ($backupSignal) {
            $backupSignal->update([
                'status' => 6,
                'updated_at' => now(),
            ]);
        }

        // ===============================
        // Discord message
        // ===============================
        if ($discordEnabled && $signal->discordMessages->count() > 0) {
            $tpMessage = "@everyone 🏆 Trading Signal reached FINAL TP5!\n\n"
                . "🆔 信号编号：{$signal->signal_code}\n"
                . "交易品种：{$signal->trading_pair}\n"
                . "入场价：{$signal->entry_price}\n"
                . "TP5：{$tpPrice}\n"
                . "总盈利：{$profitPips} pips\n"
                . $this->formatTpInsightLine($profitUsd)
                . "---------------------------------- 分割线 ----------------------------------\n";

            foreach ($signal->discordMessages as $msg) {
                $community = \App\Models\Community::find($msg->community_id);

                if (!$community || !$community->discord_webhook) {
                    $skippedCount++;
                    continue;
                }

                // 🔴 STRICT TP5 CHECK
                $tpSetting = $community->tpSettings()
                    ->where('tp_level', 5)
                    ->where('enabled', 1)
                    ->first();

                if (!$tpSetting) {
                    $skippedCount++;
                    continue;
                }

                try {
                    \Illuminate\Support\Facades\Http::post(
                        $community->discord_webhook . '?wait=true',
                        [
                            'content' => $tpMessage,
                            'allowed_mentions' => ['parse' => ['everyone']],
                        ]
                    );
                    $sentCount++;
                } catch (\Exception $e) {
                    \Log::error("Discord TP5 failed for {$community->name}: " . $e->getMessage());
                }
            }
        }

    } catch (\Exception $e) {
        \Log::error('TP5 signal update failed: ' . $e->getMessage());

        return redirect()->back()->with([
            'message' => 'Failed to mark TP5: ' . $e->getMessage(),
            'alert-type' => 'error'
        ]);
    }

    return redirect()->back()->with([
        'message' => "TP5 (FINAL) updated successfully. Profit Pips: {$profitPips}. USD Insight (0.01 lot): {$profitUsdText}. Sent: {$sentCount}, Skipped: {$skippedCount}.",
        'alert-type' => 'success'
    ]);
}

public function tp6($id)
{
    $signal = $this->signalQueryForCurrentUser(['discordMessages'])->findOrFail($id);

    // ✅ Only allow TP6 after TP5
    if ($signal->status != 6) {
        return redirect()->back()->with([
            'message' => 'Only signals marked as TP5 can be marked as TP6.',
            'alert-type' => 'info'
        ]);
    }

    $discordEnabled = feature_enabled('DiscordIntegration');
    $sentCount = 0;
    $skippedCount = 0;

    try {
        // 🔹 Validate prices
        $entryPrice = $this->parseSignalPrice($signal->entry_price);
        $tpPrice    = $this->parseSignalPrice($signal->target_6);

        if ($entryPrice <= 0 || $tpPrice <= 0) {
            return redirect()->back()->with([
                'message' => 'Entry or TP6 price is invalid.',
                'alert-type' => 'error'
            ]);
        }

        // 🔹 Calculate profit pips (once per signal code)
        $profitPips = $this->calculateProfitPips($signal->trading_pair, $entryPrice, $tpPrice);
        $profitUsd = $this->calculateInsightUsd($profitPips);
        $profitUsdText = $this->formatUsd($profitUsd);

        // ===============================
        // SignalPerformance (single row per signal)
        // ===============================
        $performance = SignalPerformance::updateOrCreate(
            ['signal_id' => $signal->id],
            [
                'tp_hit' => 6,
                'is_sl' => false,
                'is_cancelled' => false,
                'profit_pips' => $profitPips,
                'profit_usd' => $profitUsd,
                'created_at' => $signal->created_at,
                'updated_at' => now(),
            ]
        );

        // ===============================
        // Backup Performance
        // ===============================
        $backupSignal = TradingSignalBackup::where('signal_code', $signal->signal_code)->first();
        if ($backupSignal) {
            SignalPerformanceBackup::updateOrCreate(
                ['signal_id' => $backupSignal->id],
                [
                    'tp_hit' => 6,
                    'is_sl' => false,
                    'is_cancelled' => false,
                    'profit_pips' => $profitPips,
                    'profit_usd' => $profitUsd,
                    'created_at' => $signal->created_at,
                    'updated_at' => now(),
                ]
            );
        }

        // ===============================
        // Update main signal status
        // ===============================
        $signal->update([
            'status' => 7, // TP6 reached
            'updated_at' => now(),
        ]);

        if ($backupSignal) {
            $backupSignal->update([
                'status' => 7,
                'updated_at' => now(),
            ]);
        }

        // ===============================
        // Discord message (STRICT TP6)
        // ===============================
        if ($discordEnabled && $signal->discordMessages->count() > 0) {

            $tpMessage = "@everyone 🎯 Trading Signal reached TP6!\n\n"
                . "🆔 信号编号：{$signal->signal_code}\n"
                . "交易品种：{$signal->trading_pair}\n"
                . "进场价：{$signal->entry_price}\n"
                . "TP6：{$tpPrice}\n"
                . "盈利点数：{$profitPips} pips\n"
                . $this->formatTpInsightLine($profitUsd)
                . "---------------------------------- 分割线 ----------------------------------\n";

            foreach ($signal->discordMessages as $msg) {

                $community = \App\Models\Community::find($msg->community_id);

                if (!$community || !$community->discord_webhook) {
                    $skippedCount++;
                    continue;
                }

                $tpSetting = $community->tpSettings()
                    ->where('tp_level', 6)
                    ->where('enabled', 1)
                    ->first();

                if (!$tpSetting) {
                    $skippedCount++;
                    continue;
                }

                try {
                    \Illuminate\Support\Facades\Http::post(
                        $community->discord_webhook . '?wait=true',
                        [
                            'content' => $tpMessage,
                            'allowed_mentions' => ['parse' => ['everyone']],
                        ]
                    );
                    $sentCount++;
                } catch (\Exception $e) {
                    \Log::error("Discord TP6 failed for {$community->name}: " . $e->getMessage());
                }
            }
        }

    } catch (\Exception $e) {
        \Log::error('TP6 signal update failed: ' . $e->getMessage());

        return redirect()->back()->with([
            'message' => 'Failed to mark TP6: ' . $e->getMessage(),
            'alert-type' => 'error'
        ]);
    }

    return redirect()->back()->with([
        'message' => "TP6 updated successfully. Profit Pips: {$profitPips}. USD Insight (0.01 lot): {$profitUsdText}. Sent: {$sentCount}, Skipped: {$skippedCount}.",
        'alert-type' => 'success'
    ]);
}


public function tp7($id)
{
    $signal = $this->signalQueryForCurrentUser(['discordMessages'])->findOrFail($id);

    // ✅ Only allow TP7 after TP6
    if ($signal->status != 7) {
        return redirect()->back()->with([
            'message' => 'Only signals marked as TP6 can be marked as TP7.',
            'alert-type' => 'info'
        ]);
    }

    $discordEnabled = feature_enabled('DiscordIntegration');
    $sentCount = 0;
    $skippedCount = 0;

    try {
        // 🔹 Validate prices
        $entryPrice = $this->parseSignalPrice($signal->entry_price);
        $tpPrice    = $this->parseSignalPrice($signal->target_7);

        if ($entryPrice <= 0 || $tpPrice <= 0) {
            return redirect()->back()->with([
                'message' => 'Entry or TP7 price is invalid.',
                'alert-type' => 'error'
            ]);
        }

        // 🔹 Calculate profit pips (once per signal code)
        $profitPips = $this->calculateProfitPips($signal->trading_pair, $entryPrice, $tpPrice);
        $profitUsd = $this->calculateInsightUsd($profitPips);
        $profitUsdText = $this->formatUsd($profitUsd);

        // ===============================
        // SignalPerformance (single row per signal)
        // ===============================
        $performance = SignalPerformance::updateOrCreate(
            ['signal_id' => $signal->id],
            [
                'tp_hit' => 7,
                'is_sl' => false,
                'is_cancelled' => false,
                'profit_pips' => $profitPips,
                'profit_usd' => $profitUsd,
                'created_at' => $signal->created_at,
                'updated_at' => now(),
            ]
        );

        // ===============================
        // Backup Performance
        // ===============================
        $backupSignal = TradingSignalBackup::where('signal_code', $signal->signal_code)->first();
        if ($backupSignal) {
            SignalPerformanceBackup::updateOrCreate(
                ['signal_id' => $backupSignal->id],
                [
                    'tp_hit' => 7,
                    'is_sl' => false,
                    'is_cancelled' => false,
                    'profit_pips' => $profitPips,
                    'profit_usd' => $profitUsd,
                    'created_at' => $signal->created_at,
                    'updated_at' => now(),
                ]
            );
        }

        // ===============================
        // Update main & backup signal status
        // ===============================
        $signal->update([
            'status' => 8, // TP7 reached
            'updated_at' => now(),
        ]);

        if ($backupSignal) {
            $backupSignal->update([
                'status' => 8,
                'updated_at' => now(),
            ]);
        }

        // ===============================
        // Discord message (STRICT TP7)
        // ===============================
        if ($discordEnabled && $signal->discordMessages->count() > 0) {

            $tpMessage = "@everyone 🎯 Trading Signal reached TP7!\n\n"
                . "🆔 信号编号：{$signal->signal_code}\n"
                . "交易品种：{$signal->trading_pair}\n"
                . "进场价：{$signal->entry_price}\n"
                . "TP7：{$tpPrice}\n"
                . "盈利点数：{$profitPips} pips\n"
                . $this->formatTpInsightLine($profitUsd)
                . "---------------------------------- 分割线 ----------------------------------\n";

            foreach ($signal->discordMessages as $msg) {
                $community = \App\Models\Community::find($msg->community_id);

                if (!$community || !$community->discord_webhook) {
                    $skippedCount++;
                    continue;
                }

                $tpSetting = $community->tpSettings()
                    ->where('tp_level', 7)
                    ->where('enabled', 1)
                    ->first();

                if (!$tpSetting) {
                    $skippedCount++;
                    continue;
                }

                try {
                    \Illuminate\Support\Facades\Http::post(
                        $community->discord_webhook . '?wait=true',
                        [
                            'content' => $tpMessage,
                            'allowed_mentions' => ['parse' => ['everyone']],
                        ]
                    );
                    $sentCount++;
                } catch (\Exception $e) {
                    \Log::error("Discord TP7 failed for {$community->name}: " . $e->getMessage());
                }
            }
        }

    } catch (\Exception $e) {
        \Log::error('TP7 signal update failed: ' . $e->getMessage());

        return redirect()->back()->with([
            'message' => 'Failed to mark TP7: ' . $e->getMessage(),
            'alert-type' => 'error'
        ]);
    }

    return redirect()->back()->with([
        'message' => "TP7 updated successfully. Profit Pips: {$profitPips}. USD Insight (0.01 lot): {$profitUsdText}. Sent: {$sentCount}, Skipped: {$skippedCount}.",
        'alert-type' => 'success'
    ]);
}
public function tp8($id)
{
    $signal = $this->signalQueryForCurrentUser(['discordMessages'])->findOrFail($id);

    // ✅ Only allow TP8 after TP7
    if ($signal->status != 8) {
        return redirect()->back()->with([
            'message' => 'Only signals marked as TP7 can be marked as TP8.',
            'alert-type' => 'info'
        ]);
    }

    $discordEnabled = feature_enabled('DiscordIntegration');
    $sentCount = 0;
    $skippedCount = 0;

    try {
        // 🔹 Validate prices
        $entryPrice = $this->parseSignalPrice($signal->entry_price);
        $tpPrice    = $this->parseSignalPrice($signal->target_8);

        if ($entryPrice <= 0 || $tpPrice <= 0) {
            return redirect()->back()->with([
                'message' => 'Entry or TP8 price is invalid.',
                'alert-type' => 'error'
            ]);
        }

        // 🔹 Calculate profit pips (once per signal code)
        $profitPips = $this->calculateProfitPips($signal->trading_pair, $entryPrice, $tpPrice);
        $profitUsd = $this->calculateInsightUsd($profitPips);
        $profitUsdText = $this->formatUsd($profitUsd);

        // ===============================
        // SignalPerformance (single row per signal)
        // ===============================
        $performance = SignalPerformance::updateOrCreate(
            ['signal_id' => $signal->id],
            [
                'tp_hit' => 8,
                'is_sl' => false,
                'is_cancelled' => false,
                'profit_pips' => $profitPips,
                'profit_usd' => $profitUsd,
                'created_at' => $signal->created_at,
                'updated_at' => now(),
            ]
        );

        // ===============================
        // Backup Performance
        // ===============================
        $backupSignal = TradingSignalBackup::where('signal_code', $signal->signal_code)->first();
        if ($backupSignal) {
            SignalPerformanceBackup::updateOrCreate(
                ['signal_id' => $backupSignal->id],
                [
                    'tp_hit' => 8,
                    'is_sl' => false,
                    'is_cancelled' => false,
                    'profit_pips' => $profitPips,
                    'profit_usd' => $profitUsd,
                    'created_at' => $signal->created_at,
                    'updated_at' => now(),
                ]
            );
        }

        // ===============================
        // Update main & backup signal status
        // ===============================
        $signal->update([
            'status' => 9, // TP8 reached
            'updated_at' => now(),
        ]);

        if ($backupSignal) {
            $backupSignal->update([
                'status' => 9,
                'updated_at' => now(),
            ]);
        }

        // ===============================
        // Discord message (STRICT TP8)
        // ===============================
        if ($discordEnabled && $signal->discordMessages->count() > 0) {

            $tpMessage = "@everyone 🎯 Trading Signal reached TP8!\n\n"
                . "🆔 信号编号：{$signal->signal_code}\n"
                . "交易品种：{$signal->trading_pair}\n"
                . "进场价：{$signal->entry_price}\n"
                . "TP8：{$tpPrice}\n"
                . "盈利点数：{$profitPips} pips\n"
                . $this->formatTpInsightLine($profitUsd)
                . "---------------------------------- 分割线 ----------------------------------\n";

            foreach ($signal->discordMessages as $msg) {
                $community = \App\Models\Community::find($msg->community_id);

                if (!$community || !$community->discord_webhook) {
                    $skippedCount++;
                    continue;
                }

                $tpSetting = $community->tpSettings()
                    ->where('tp_level', 8)
                    ->where('enabled', 1)
                    ->first();

                if (!$tpSetting) {
                    $skippedCount++;
                    continue;
                }

                try {
                    \Illuminate\Support\Facades\Http::post(
                        $community->discord_webhook . '?wait=true',
                        [
                            'content' => $tpMessage,
                            'allowed_mentions' => ['parse' => ['everyone']],
                        ]
                    );
                    $sentCount++;
                } catch (\Exception $e) {
                    \Log::error("Discord TP8 failed for {$community->name}: " . $e->getMessage());
                }
            }
        }

    } catch (\Exception $e) {
        \Log::error('TP8 signal update failed: ' . $e->getMessage());
        return redirect()->back()->with([
            'message' => 'Failed to mark TP8: ' . $e->getMessage(),
            'alert-type' => 'error'
        ]);
    }

    return redirect()->back()->with([
        'message' => "TP8 updated successfully. Profit Pips: {$profitPips}. USD Insight (0.01 lot): {$profitUsdText}. Sent: {$sentCount}, Skipped: {$skippedCount}.",
        'alert-type' => 'success'
    ]);
}

public function tp9($id)
{
    $signal = $this->signalQueryForCurrentUser(['discordMessages'])->findOrFail($id);

    // ✅ Only allow TP9 after TP8
    if ($signal->status != 9) {
        return redirect()->back()->with([
            'message' => 'Only signals marked as TP8 can be marked as TP9.',
            'alert-type' => 'info'
        ]);
    }

    $discordEnabled = feature_enabled('DiscordIntegration');
    $sentCount = 0;
    $skippedCount = 0;

    try {
        // 🔹 Validate prices
        $entryPrice = $this->parseSignalPrice($signal->entry_price);
        $tpPrice    = $this->parseSignalPrice($signal->target_9);

        if ($entryPrice <= 0 || $tpPrice <= 0) {
            return redirect()->back()->with([
                'message' => 'Entry or TP9 price is invalid.',
                'alert-type' => 'error'
            ]);
        }

        // 🔹 Calculate profit pips (XAU/USD)
        $profitPips = $this->calculateProfitPips($signal->trading_pair, $entryPrice, $tpPrice);
        $profitUsd = $this->calculateInsightUsd($profitPips);
        $profitUsdText = $this->formatUsd($profitUsd);

        // ===============================
        // SignalPerformance (TP9)
        // ===============================
        SignalPerformance::updateOrCreate(
            ['signal_id' => $signal->id],
            [
                'tp_hit'       => 9,
                'is_sl'        => false,
                'is_cancelled' => false,
                'profit_pips'  => $profitPips,
                'profit_usd'   => $profitUsd,
                'created_at'   => $signal->created_at,
                'updated_at'   => now(),
            ]
        );

        // ===============================
        // SignalPerformanceBackup (TP9)
        // ===============================
        $backupSignal = TradingSignalBackup::where('signal_code', $signal->signal_code)->first();
        if ($backupSignal) {
            SignalPerformanceBackup::updateOrCreate(
                ['signal_id' => $backupSignal->id],
                [
                    'tp_hit'       => 9,
                    'is_sl'        => false,
                    'is_cancelled' => false,
                    'profit_pips'  => $profitPips,
                    'profit_usd'   => $profitUsd,
                    'created_at'   => $signal->created_at,
                    'updated_at'   => now(),
                ]
            );
        }

        // 🔹 Update main & backup signal status
        $signal->update([
            'status'     => 10, // TP9 reached
            'updated_at' => now(),
        ]);

        if ($backupSignal) {
            $backupSignal->update([
                'status'     => 10,
                'updated_at' => now(),
            ]);
        }

        // 🔹 Discord TP9 (STRICT)
        if ($discordEnabled && $signal->discordMessages->count() > 0) {

            $tpMessage = "@everyone 🎯 Trading Signal reached TP9!\n\n"
                . "🆔 信号编号：{$signal->signal_code}\n"
                . "交易品种：{$signal->trading_pair}\n"
                . "进场价：{$signal->entry_price}\n"
                . "TP9：{$tpPrice}\n"
                . "盈利点数：{$profitPips} pips\n"
                . $this->formatTpInsightLine($profitUsd)
                . "---------------------------------- 分割线 ----------------------------------\n";

            foreach ($signal->discordMessages as $msg) {
                $community = \App\Models\Community::find($msg->community_id);

                if (!$community || !$community->discord_webhook) {
                    $skippedCount++;
                    continue;
                }

                $tpSetting = $community->tpSettings()
                    ->where('tp_level', 9)
                    ->where('enabled', 1)
                    ->first();

                if (!$tpSetting) {
                    $skippedCount++;
                    continue;
                }

                try {
                    \Illuminate\Support\Facades\Http::post(
                        $community->discord_webhook . '?wait=true',
                        [
                            'content' => $tpMessage,
                            'allowed_mentions' => ['parse' => ['everyone']],
                        ]
                    );
                    $sentCount++;
                } catch (\Exception $e) {
                    \Log::error("Discord TP9 failed for {$community->name}: " . $e->getMessage());
                }
            }
        }

    } catch (\Exception $e) {
        \Log::error('TP9 signal update failed: ' . $e->getMessage());
        return redirect()->back()->with([
            'message' => 'Failed to mark TP9: ' . $e->getMessage(),
            'alert-type' => 'error'
        ]);
    }

    return redirect()->back()->with([
        'message' => "TP9 updated successfully. Profit Pips: {$profitPips}. USD Insight (0.01 lot): {$profitUsdText}. Sent: {$sentCount}, Skipped: {$skippedCount}.",
        'alert-type' => 'success'
    ]);
}

public function tp10($id)
{
    $signal = $this->signalQueryForCurrentUser(['discordMessages'])->findOrFail($id);

    // ✅ Only allow TP10 after TP9
    if ($signal->status != 10) {
        return redirect()->back()->with([
            'message' => 'Only signals marked as TP9 can be marked as TP10.',
            'alert-type' => 'info'
        ]);
    }

    $discordEnabled = feature_enabled('DiscordIntegration');
    $sentCount = 0;
    $skippedCount = 0;

    try {
        // 🔹 Validate prices
        $entryPrice = $this->parseSignalPrice($signal->entry_price);
        $tpPrice    = $this->parseSignalPrice($signal->target_10);

        if ($entryPrice <= 0 || $tpPrice <= 0) {
            return redirect()->back()->with([
                'message' => 'Entry or TP10 price is invalid.',
                'alert-type' => 'error'
            ]);
        }

        // 🔹 Calculate profit pips
        $profitPips = $this->calculateProfitPips($signal->trading_pair, $entryPrice, $tpPrice);
        $profitUsd = $this->calculateInsightUsd($profitPips);
        $profitUsdText = $this->formatUsd($profitUsd);

        // ===============================
        // SignalPerformance (TP10)
        // ===============================
        $performance = SignalPerformance::firstOrNew([
            'signal_id' => $signal->id,
        ]);

        $performance->tp_hit       = 10;
        $performance->is_sl        = false;
        $performance->is_cancelled = false;
        $performance->profit_pips  = $profitPips;
        $performance->profit_usd   = $profitUsd;

        if (!$performance->exists) {
            $performance->created_at = $signal->created_at;
        }

        $performance->updated_at = now();
        $performance->save();

        // ===============================
        // SignalPerformanceBackup (TP10)
        // ===============================
        $backupSignal = TradingSignalBackup::where('signal_code', $signal->signal_code)->first();

        if ($backupSignal) {
            $backup = SignalPerformanceBackup::firstOrNew([
                'signal_id' => $backupSignal->id,
            ]);

            $backup->tp_hit       = 10;
            $backup->is_sl        = false;
            $backup->is_cancelled = false;
            $backup->profit_pips  = $profitPips;
            $backup->profit_usd   = $profitUsd;

            if (!$backup->exists) {
                $backup->created_at = $signal->created_at;
            }

            $backup->updated_at = now();
            $backup->save();
        }

        // 🔹 Update main signal status
        $signal->update([
            'status'     => 11, // TP10 reached
            'updated_at' => now(),
        ]);

        // 🔹 Update backup signals
        TradingSignalBackup::where('signal_code', $signal->signal_code)
            ->update([
                'status'     => 11,
                'updated_at' => now(),
            ]);

        // 🔹 Prepare Discord message
        $tpMessage = "@everyone 🏆 Trading Signal reached TP10 — Full TP hit!\n\n"
            . "🆔 信号编号：{$signal->signal_code}\n"
            . "交易品种：{$signal->trading_pair}\n"
            . "进场价：{$signal->entry_price}\n"
            . "TP10：{$tpPrice}\n"
            . "盈利点数：{$profitPips} pips\n"
                . $this->formatTpInsightLine($profitUsd)
            . "---------------------------------- 分割线 ----------------------------------\n"
            . "Manage your position and review the trade.";

        // 🔹 Send Discord messages only to previous communities
        if ($discordEnabled && $signal->discordMessages->count() > 0) {
            foreach ($signal->discordMessages as $msg) {
                $community = \App\Models\Community::find($msg->community_id);

                if (!$community || !$community->discord_webhook) {
                    $skippedCount++;
                    continue;
                }

                $tpSetting = $community->tpSettings()
                    ->where('tp_level', 10)
                    ->where('enabled', 1)
                    ->first();

                if (!$tpSetting) {
                    $skippedCount++;
                    continue;
                }

                try {
                    \Illuminate\Support\Facades\Http::post(
                        $community->discord_webhook . '?wait=true',
                        [
                            'content' => $tpMessage,
                            'allowed_mentions' => ['parse' => ['everyone']],
                        ]
                    );
                    $sentCount++;
                } catch (\Exception $e) {
                    \Log::error("Discord TP10 failed for {$community->name}: " . $e->getMessage());
                }
            }
        }

    } catch (\Exception $e) {
        \Log::error('TP10 signal update failed: ' . $e->getMessage());
        return redirect()->back()->with([
            'message' => 'Failed to mark TP10: ' . $e->getMessage(),
            'alert-type' => 'error'
        ]);
    }

    return redirect()->back()->with([
        'message' => "TP10 updated successfully. Profit Pips: {$profitPips}. USD Insight (0.01 lot): {$profitUsdText}. Sent: {$sentCount}, Skipped: {$skippedCount}.",
        'alert-type' => 'success'
    ]);
}

public function setBE($id)
{
    $signal = $this->signalQueryForCurrentUser()->findOrFail($id);

    // ------------------------------------
    // Validation: Only Active / TP signals
    // ------------------------------------
    if (!in_array($signal->status, range(1, 11))) {
        return redirect()->back()->with([
            'message'    => 'Only active signals can be set to Breakeven.',
            'alert-type' => 'info',
        ]);
    }

    try {
        // ------------------------------------
        // Update Trading Signal
        // ------------------------------------
        $signal->update([
            'IsSetBE' => 1,
        ]);

        // ------------------------------------
        // Update Backup Table
        // ------------------------------------
        TradingSignalBackup::where('signal_code', $signal->signal_code)
            ->update([
                'IsSetBE' => 1,
            ]);

        // ------------------------------------
        // Discord Integration
        // ------------------------------------
        if (feature_enabled('DiscordIntegration')) {

            $message =
                "⚖️ 该交易信号请进入设置保本 (Set BE)\n\n" .
                "🆔 信号编号：{$signal->signal_code}\n" .
                "交易品种：{$signal->trading_pair}\n" .
                "即时操作：{$signal->immediate_action} {$signal->entry_price}\n" .
                "请根据策略进行操作\n" .
                "---------------------------------- 分割线 ----------------------------------";

            // Get cached discord messages (already linked to community_id)
            $discordRecords = TradingSignalDiscord::where(
                'trading_signal_id',
                $signal->id
            )->get();

            foreach ($discordRecords as $record) {

                $community = Community::find($record->community_id);

                if (!$community || !$community->discord_webhook) {
                    continue;
                }

                $discordMessage = '';

                // Everyone mention control
                if (
                    feature_enabled('DiscordIntegration_Everyone') &&
                    $community->discord_everyone_enabled
                ) {
                    $discordMessage .= "@everyone\n\n";
                }

                $discordMessage .= $message;

                DiscordService::send(
                    $discordMessage,
                    $community->discord_webhook
                );
            }
        }

    } catch (\Exception $e) {

        \Log::error('Set BE failed', [
            'signal_id' => $signal->id,
            'error'     => $e->getMessage(),
        ]);

        return redirect()->back()->with([
            'message'    => 'Failed to set BE: ' . $e->getMessage(),
            'alert-type' => 'error',
        ]);
    }

    return redirect()->back()->with([
        'message'    => 'Trading Signal has been announced as Set BE.',
        'alert-type' => 'success',
    ]);
}

public function beHitted($id)
{
    $signal = $this->signalQueryForCurrentUser()->findOrFail($id);

    // Only allow if Set BE has been announced
    if (!$signal->IsSetBE) {
        return redirect()->back()->with([
            'message' => 'You must announce Set BE first before marking BE Hitted.',
            'alert-type' => 'info'
        ]);
    }

    $discordEnabled = feature_enabled('DiscordIntegration');

    try {
        // 🔹 Update main signal
        $signal->update([
            'status' => 15, // BE Hitted status
            'IsBE'  => 1,   // Mark as BE hit
            'updated_at' => now(),
        ]);

        // 🔹 Update backup table
        TradingSignalBackup::where('signal_code', $signal->signal_code)
            ->update([
                'status' => 15,
                'IsBE'  => 1,
                'updated_at' => now(),
            ]);

        // 🔹 Update SignalPerformance per community
        foreach ($signal->discordMessages as $msg) {
            $community = \App\Models\Community::where('name', $msg->community)->first();
            if (!$community) continue;

            $performance = \App\Models\SignalPerformance::firstOrNew([
                'signal_id'    => $signal->id,
                'community_id' => $community->id,
            ]);

            $performance->tp_hit       = null;      // Not a TP
            $performance->is_sl        = false;
            $performance->is_tp        = false;
            $performance->is_be        = true;
            $performance->profit_pips  = 2;         // Default pip for BE
            $performance->entry_price  = $signal->entry_price;
            $performance->created_at   = $signal->created_at;
            $performance->updated_at   = now();
            $performance->save();
        }

        // 🔹 Discord notification
        if ($discordEnabled) {
            $message = "@everyone ⚖️ 该交易信号已保本 (BE Hitted)。\n"
                     . "🆔 信号编号：{$signal->signal_code}\n"
                     . "交易品种：{$signal->trading_pair}\n"
                     . "即时操作：{$signal->immediate_action} {$signal->entry_price}\n"
                     . "---------------------------------- 分割线 ----------------------------------\n";

            foreach ($signal->discordMessages as $msg) {
                $community = \App\Models\Community::where('name', $msg->community)->first();
                if (!$community || !$community->discord_webhook) continue;

                \Illuminate\Support\Facades\Http::post($community->discord_webhook . "?wait=true", [
                    'content' => $message,
                    'allowed_mentions' => ['parse' => ['everyone']],
                ]);
            }
        }

    } catch (\Exception $e) {
        \Log::error('BE Hitted failed: ' . $e->getMessage());
        return redirect()->back()->with([
            'message' => 'Failed to mark BE Hitted: ' . $e->getMessage(),
            'alert-type' => 'error'
        ]);
    }

    return redirect()->back()->with([
        'message' => 'Trading Signal marked as BE Hitted successfully.',
        'alert-type' => 'success'
    ]);
}


public function show($id)
{
    // Breadcrumb for navigation
    $breadcrumbData = [
        ['label' => 'HC Gaming', 'url' => route('all.statistics')],
        ['label' => 'Trading Signals', 'url' => route('all.trading.signals')],
        ['label' => 'View Details', 'url' => '#'],
    ];

    // Load signal with community relation
    $signal = $this->signalQueryForCurrentUser(['community', 'user', 'discordMessages'])->findOrFail($id);

    return view('admin.trading_signal.signals_view', compact('signal', 'breadcrumbData'));
}
public function memberSignals(Request $request)
{
    $statusLabels = [
        0  => 'Pending',
        1  => 'Active',
        2  => 'TP1',
        3  => 'TP2',
        4  => 'TP3',
        5  => 'TP4',
        6  => 'TP5',
        7  => 'TP6',
        8  => 'TP7',
        9  => 'TP8',
        10 => 'TP9',
        11 => 'TP10',
        12 => 'Cancelled',
        13 => 'SL',
        14 => 'Done',
    ];

    $breadcrumbData = [
        ['label' => 'Trading Signals', 'url' => route('member.trading.signals')]
    ];

    // Base Query (Members see all signals)
    $query = TradingSignal::with('user');

    // ================= DATE FILTER =================
    if ($request->filled('from_date') && $request->filled('to_date')) {
        $from = Carbon::parse($request->from_date)->startOfDay();
        $to   = Carbon::parse($request->to_date)->endOfDay();
        $query->whereBetween('created_at', [$from, $to]);
    } elseif ($request->filled('quick_range')) {

        switch ($request->quick_range) {

            case 'today':
                $query->whereDate('created_at', Carbon::today());
                break;

            case '7days':
                $query->where('created_at', '>=', Carbon::now()->subDays(6)->startOfDay());
                break;

            case '30days':
                $query->where('created_at', '>=', Carbon::now()->subDays(29)->startOfDay());
                break;

            case '90days':
                $query->where('created_at', '>=', Carbon::now()->subDays(89)->startOfDay());
                break;
        }
    }

    // Fetch signals
    $signals = $query->latest()->get();

    // ================= STATISTICS =================

    $totalSignals = $signals->count();

    $totalTP = $signals->whereBetween('status', [2, 11])->count();

    $totalSL = $signals->where('status', 13)->count();

    $totalCancel = $signals->where('status', 12)->count();

    $totalBE = $signals->where('IsBE', 1)->count();

    $totalDone = $signals->where('IsDone', 1)->count();


    return view('traders.member_signal.members_signals_all', [

        'signals'       => $signals,
        'statusLabels'  => $statusLabels,
        'breadcrumbData'=> $breadcrumbData,

        'totalSignals'  => $totalSignals,
        'totalTP'       => $totalTP,
        'totalSL'       => $totalSL,
        'totalCancel'   => $totalCancel,
        'totalBE'       => $totalBE,
        'totalDone'     => $totalDone,

        'from_date'     => $request->from_date,
        'to_date'       => $request->to_date,
        'quick_range'   => $request->quick_range,
    ]);
}

 public function memberDashboard()
{
    $signals = TradingSignal::where('status', '>=', 1)
        ->orderBy('created_at', 'desc')
        ->get();

    $statusMap = [
        0  => 'Pending',
        1  => 'Active',
        2  => 'TP1 Achieved',
        3  => 'TP2 Achieved',
        4  => 'TP3 Achieved',
        5  => 'TP4 Achieved',
        6  => 'TP5 Achieved',
        7  => 'TP6 Achieved',
        8  => 'TP7 Achieved',
        9  => 'TP8 Achieved',
        10 => 'TP9 Achieved',
        11 => 'TP10 Achieved',
        12 => 'Cancelled',
        13 => 'SL Hit',
        14 => 'Done',
        15 => 'BE',
    ];

    $totalSignals = $signals->count();
    $totalTP = $signals->whereBetween('status', [2, 11])->count();
    $totalSL = $signals->where('status', 13)->count();
    $totalCancel = $signals->where('status', 12)->count();
    $totalBE = $signals->filter(fn ($signal) => (int) $signal->IsBE === 1 || (int) $signal->status === 15)->count();
    $totalDone = $signals->filter(fn ($signal) => (int) $signal->IsDone === 1 || (int) $signal->status === 14)->count();
    $totalActive = $signals->filter(fn ($signal) => (int) $signal->status === 1 && (int) $signal->IsDone !== 1)->count();
    $todaySignals = $signals->filter(fn ($signal) => $signal->created_at && $signal->created_at->isToday())->count();

    $tpRate = $totalSignals > 0 ? round(($totalTP / $totalSignals) * 100, 1) : 0;
    $slRate = $totalSignals > 0 ? round(($totalSL / $totalSignals) * 100, 1) : 0;
    $beRate = $totalSignals > 0 ? round(($totalBE / $totalSignals) * 100, 1) : 0;
    $completionRate = $totalSignals > 0 ? round(($totalDone / $totalSignals) * 100, 1) : 0;

    $statusCounts = [
        'Active' => $totalActive,
        'TP Achieved' => $totalTP,
        'BE' => $totalBE,
        'SL' => $totalSL,
        'Cancelled' => $totalCancel,
        'Done' => $totalDone,
    ];

    $tpCounts = collect(range(2, 11))
        ->mapWithKeys(fn ($status) => ['TP' . ($status - 1) => $signals->where('status', $status)->count()])
        ->toArray();

    $pairStats = $signals
        ->groupBy(fn ($signal) => strtoupper($signal->trading_pair ?: 'Unknown'))
        ->map(fn ($items, $pair) => [
            'pair' => $pair,
            'total' => $items->count(),
            'tp' => $items->whereBetween('status', [2, 11])->count(),
            'sl' => $items->where('status', 13)->count(),
            'active' => $items->where('status', 1)->count(),
        ])
        ->sortByDesc('total')
        ->take(6)
        ->values();

    $riskStats = $signals
        ->groupBy(fn ($signal) => $signal->risk_level ?: 'Unrated')
        ->map(fn ($items, $risk) => [
            'risk' => $risk,
            'total' => $items->count(),
        ])
        ->sortByDesc('total')
        ->values();

    $dailySignalTrend = collect(range(6, 0))
        ->map(function ($daysAgo) use ($signals) {
            $date = Carbon::today()->subDays($daysAgo);

            return [
                'label' => $date->format('M d'),
                'total' => $signals->filter(fn ($signal) => $signal->created_at && $signal->created_at->isSameDay($date))->count(),
            ];
        });

    $highestTpStatus = $signals
        ->filter(fn ($signal) => (int) $signal->status >= 2 && (int) $signal->status <= 11)
        ->max('status');
    $bestTpLevel = $highestTpStatus ? max(1, (int) $highestTpStatus - 1) : 0;
    $tpProgressPercent = $bestTpLevel > 0 ? min(100, $bestTpLevel * 10) : 0;
    $recentSignals = $signals->take(8);
    $latestSignal = $signals->first();

    return view('traders.member_signal.members_signals_dashboard', compact(
        'signals',
        'recentSignals',
        'latestSignal',
        'totalSignals',
        'totalActive',
        'totalTP',
        'totalSL',
        'totalCancel',
        'totalBE',
        'totalDone',
        'todaySignals',
        'tpRate',
        'slRate',
        'beRate',
        'completionRate',
        'bestTpLevel',
        'tpProgressPercent',
        'statusMap',
        'statusCounts',
        'tpCounts',
        'pairStats',
        'riskStats',
        'dailySignalTrend'
    ));
}

public function memberViewSignal($id)
{
    $signal = TradingSignal::with(['community', 'user'])
        ->where('status', '>=', 1)
        ->findOrFail($id);

    $statusMap = [
        0  => 'Pending',
        1  => 'Active',
        2  => 'TP1',
        3  => 'TP2',
        4  => 'TP3',
        5  => 'TP4',
        6  => 'TP5',
        7  => 'TP6',
        8  => 'TP7',
        9  => 'TP8',
        10 => 'TP9',
        11 => 'TP10',
        12 => 'Cancelled',
        13 => 'SL',
        14 => 'Done',
        15 => 'BE',
    ];

    $breadcrumbData = [
        ['label' => 'HC Gaming', 'url' => route('all.statistics')],
        ['label' => 'Signal Dashboard', 'url' => route('member.signals.dashboard')],
        ['label' => 'Signal Details', 'url' => route('member.signals.view', $signal->id)],
    ];

    return view('traders.member_signal.members_signals_view', compact(
        'signal',
        'statusMap',
        'breadcrumbData'
    ));
}
// Member Active Signals
public function memberActiveSignals()
{
    // Fetch ALL active signals (In Progress: IsDone = 0)
    // and explicitly exclude Cancelled (12), SL (13), and Done (14) statuses
    $signals = TradingSignal::where('IsDone', 0)
        ->whereNotIn('status', [12, 13, 14])
        ->orderBy('created_at', 'desc')
        ->get();

    // Status map
    $statusLabels = [
        0  => 'Pending',
        1  => 'Active',
        2  => 'TP1',
        3  => 'TP2',
        4  => 'TP3',
        5  => 'TP4',
        6  => 'TP5',
        7  => 'TP6',
        8  => 'TP7',
        9  => 'TP8',
        10 => 'TP9',
        11 => 'TP10',
        12 => 'Cancelled',
        13 => 'SL',
        14 => 'Done',
        15 => 'BE',
    ];

    // Summary counts (Based ONLY on the active signals fetched above)
    $totalSignals = $signals->count();
    $totalTP = $signals->whereBetween('status', [2, 11])->count();
    $totalBE = $signals->where('status', 15)->count();

    // These remain 0 because this specific page only loads active signals
    $totalSL = 0;
    $totalCancel = 0;
    $totalDone = 0;

    return view('traders.member_signal.members_signals_active', compact(
        'signals',
        'statusLabels',
        'totalSignals',
        'totalTP',
        'totalSL',
        'totalCancel',
        'totalBE',
        'totalDone'
    ));
}

public function memberClosedSignals()
{
    $statusLabels = [
        0  => 'Pending',
        1  => 'Active',
        2  => 'TP1',
        3  => 'TP2',
        4  => 'TP3',
        5  => 'TP4',
        6  => 'TP5',
        7  => 'TP6',
        8  => 'TP7',
        9  => 'TP8',
        10 => 'TP9',
        11 => 'TP10',
        12 => 'Cancelled',
        13 => 'SL',
        14 => 'Done',
        15 => 'BE',
    ];

    $signals = TradingSignal::with('user')
        ->where(function ($query) {
            $query
                ->whereIn('status', [12, 13, 14, 15])
                ->orWhere('IsDone', 1)
                ->orWhere('IsBE', 1);
        })
        ->orderBy('created_at', 'desc')
        ->get();

    $pageTitle = 'Closed Signals';
    $pageSubtitle = 'Completed, cancelled, SL, and BE signal outcomes.';
    $resetRoute = route('member.signals.closed');
    $showFilters = false;

    $totalSignals = $signals->count();
    $totalTP = $signals->whereBetween('status', [2, 11])->count();
    $totalSL = $signals->where('status', 13)->count();
    $totalCancel = $signals->where('status', 12)->count();
    $totalBE = $signals->filter(fn ($signal) => (int) $signal->IsBE === 1 || (int) $signal->status === 15)->count();
    $totalDone = $signals->filter(fn ($signal) => (int) $signal->IsDone === 1 || (int) $signal->status === 14)->count();

    return view('traders.member_signal.members_signals_history', compact(
        'signals',
        'statusLabels',
        'pageTitle',
        'pageSubtitle',
        'resetRoute',
        'showFilters',
        'totalSignals',
        'totalTP',
        'totalSL',
        'totalCancel',
        'totalBE',
        'totalDone'
    ));
}

public function memberSignalHistory(Request $request)
{
    $statusLabels = [
        0  => 'Pending',
        1  => 'Active',
        2  => 'TP1',
        3  => 'TP2',
        4  => 'TP3',
        5  => 'TP4',
        6  => 'TP5',
        7  => 'TP6',
        8  => 'TP7',
        9  => 'TP8',
        10 => 'TP9',
        11 => 'TP10',
        12 => 'Cancelled',
        13 => 'SL',
        14 => 'Done',
        15 => 'BE',
    ];

    $query = TradingSignal::with('user')->where('status', '>=', 1);

    if ($request->filled('from_date') && $request->filled('to_date')) {
        $from = Carbon::parse($request->from_date)->startOfDay();
        $to = Carbon::parse($request->to_date)->endOfDay();
        $query->whereBetween('created_at', [$from, $to]);
    } elseif ($request->filled('quick_range')) {
        $range = $request->quick_range;

        if ($range === 'today') {
            $query->whereDate('created_at', Carbon::today());
        } elseif ($range === '7days') {
            $query->where('created_at', '>=', Carbon::now()->subDays(7));
        } elseif ($range === '30days') {
            $query->where('created_at', '>=', Carbon::now()->subDays(30));
        } elseif ($range === '90days') {
            $query->where('created_at', '>=', Carbon::now()->subDays(90));
        }
    }

    $signals = $query->orderBy('created_at', 'desc')->get();

    $pageTitle = 'Signal History';
    $pageSubtitle = 'Complete signal archive for review and learning.';
    $resetRoute = route('member.signals.history');
    $showFilters = true;

    $totalSignals = $signals->count();
    $totalTP = $signals->whereBetween('status', [2, 11])->count();
    $totalSL = $signals->where('status', 13)->count();
    $totalCancel = $signals->where('status', 12)->count();
    $totalBE = $signals->filter(fn ($signal) => (int) $signal->IsBE === 1 || (int) $signal->status === 15)->count();
    $totalDone = $signals->filter(fn ($signal) => (int) $signal->IsDone === 1 || (int) $signal->status === 14)->count();

    return view('traders.member_signal.members_signals_history', compact(
        'signals',
        'statusLabels',
        'pageTitle',
        'pageSubtitle',
        'resetRoute',
        'showFilters',
        'totalSignals',
        'totalTP',
        'totalSL',
        'totalCancel',
        'totalBE',
        'totalDone'
    ));
}
    /**
     * Delete trading signal
     */
    public function destroy($id)
    {
        $this->signalQueryForCurrentUser()->findOrFail($id)->delete();

        return redirect()->back()->with([
            'message' => 'Trading Signal Deleted Successfully',
            'alert-type' => 'success'
        ]);
    }

}
