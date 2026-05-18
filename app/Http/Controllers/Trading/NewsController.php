<?php

namespace App\Http\Controllers\Trading;

use App\Http\Controllers\Controller;
use App\Models\News;
use App\Models\NewsDiscord;

use App\Models\Community;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;

class NewsController extends Controller
{
    /**
     * Display all news
     */
    public function index()
    {
        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'News', 'url' => route('trading.news.index')],
        ];

        $news = News::with('community')->orderBy('news_date', 'desc')->get();
        $totalNews = News::count();

        return view('admin.forex_news.news_all', compact('news', 'breadcrumbData', 'totalNews'));
    }

    /**
     * Show create form
     */
    public function create()
    {
        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'News', 'url' => route('trading.news.index')],
            ['label' => 'Add News', 'url' => route('trading.news.create')],
        ];

        $communities = Community::where('status', 1)->get();

        return view('admin.forex_news.news_add', compact('breadcrumbData', 'communities'));
    }

    /**
     * Store new news
     */
 public function store(Request $request)
{
    // Validate input
    $data = $request->validate([
        'news_date' => 'required|date',
        'impact' => 'required|integer|in:1,2,3',
        'community_id' => 'required|exists:communities,id',
        'image' => 'nullable|image|max:2048',
    ]);

    $imagePath = null;

    // Handle image upload
    if ($request->hasFile('image')) {
        $image = $request->file('image');
        $imageName = time() . '_' . str_replace(' ', '_', $image->getClientOriginalName());
        $destinationPath = public_path('upload/news');
        if (!file_exists($destinationPath)) mkdir($destinationPath, 0755, true);
        $image->move($destinationPath, $imageName);
        $imagePath = 'upload/news/' . $imageName;
    }

    // Define professional guidance per impact level in Chinese
    $impactGuidance = [
        1 => [
            'label' => '低',
            'trader_action' => '市场波动较小，建议小仓位操作，避免过度杠杆。',
            'momentum' => '行情动量有限，价格波动较缓慢。'
        ],
        2 => [
            'label' => '中',
            'trader_action' => '预期中等波动，关注关键支撑/阻力位，设置合理止损。',
            'momentum' => '可能出现中等动量，需密切关注趋势方向。'
        ],
        3 => [
            'label' => '高',
            'trader_action' => '市场可能剧烈波动，重点关注重要价位，严格风险管理。',
            'momentum' => '强劲动量可能出现，价格快速变动风险高。'
        ]
    ];

    $impact = $data['impact'];
    $impactText = $impactGuidance[$impact]['label'];
    $traderAction = $impactGuidance[$impact]['trader_action'];
    $momentum = $impactGuidance[$impact]['momentum'];

    /// Auto-generate content in Chinese with additional warning
$newsDate = date('Y-m-d', strtotime($data['news_date'])); // 格式化日期
$content = "📅 **日期:** $newsDate\n\n"; // first line

$content .= "📰 **新闻影响力:** $impactText\n";
$content .= "💡 **交易建议:** $traderAction\n";
$content .= "📈 **预期行情动量:** $momentum\n";
$content .= "⚠️ **温馨提醒:** 做交易之前，请谨慎评估当天新闻，别因为新闻破坏交易计划，务必做好风险管理。\n";
$content .= "---------------------------------- 分割线 ----------------------------------\n";

    // Save to database
    $news = News::create([
        'content' => $content,
        'impact' => $impact,
        'news_date' => $data['news_date'],
        'image' => $imagePath,
        'community_id' => $data['community_id'],
    ]);

    // Send to Discord if integration enabled
    if (feature_enabled('DiscordIntegration')) {
        $this->sendToDiscord($news);
    }

    return redirect()->route('trading.news.index')->with('success', '新闻已成功添加！');
}

    /**
     * Show edit form
     */
    public function edit($id)
    {
        $news = News::findOrFail($id);
        $communities = Community::where('status', 1)->get();

        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'News', 'url' => route('trading.news.index')],
            ['label' => 'Edit News', 'url' => route('trading.news.edit', $news->id)],
        ];

        return view('admin.forex_news.news_edit', compact('news', 'breadcrumbData', 'communities'));
    }

    /**
     * Update news
     */
    public function update(Request $request, $id)
    {
        $news = News::findOrFail($id);

        $data = $request->validate([
            'news_date' => 'required|date',
            'impact' => 'required|integer|in:1,2,3',
            'community_id' => 'required|exists:communities,id',
            'image' => 'nullable|image|max:2048',
        ]);

        // Handle image upload
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . str_replace(' ', '_', $image->getClientOriginalName());
            $destinationPath = public_path('upload/news');
            if (!file_exists($destinationPath)) mkdir($destinationPath, 0755, true);
            $image->move($destinationPath, $imageName);

            // Delete old image
            if ($news->image && file_exists(public_path($news->image))) {
                unlink(public_path($news->image));
            }

            $news->image = 'upload/news/' . $imageName;
        }

        // Update fields
        $impactLabels = [1 => 'Low', 2 => 'Medium', 3 => 'High'];
        $impactText = $impactLabels[$data['impact']];
        $news->impact = $data['impact'];
        $news->news_date = $data['news_date'];
        $news->community_id = $data['community_id'];
        $news->content = "Hi guys, today have $impactText impact news for USD.";
        $news->save();

        // Update Discord
        if (feature_enabled('DiscordIntegration')) {
            $this->sendToDiscord($news);
        }

        return redirect()->route('trading.news.index')->with('success', 'News updated successfully!');
    }

    /**
     * Delete news
     */
    public function destroy($id)
    {
        $news = News::findOrFail($id);

        // Delete image if exists
        if ($news->image && file_exists(public_path($news->image))) {
            unlink(public_path($news->image));
        }

        $news->delete();

        return redirect()->route('trading.news.index')->with('success', 'News deleted successfully!');
    }

    /**
     * Send news to Discord (community webhook)
     */
    
public function sendToDiscord(News $news)
{
    if (!feature_enabled('DiscordIntegration')) return;

    // Determine which communities to send
    $communities = $news->community_id === 'all'
        ? Community::where('status', 1)->get()
        : Community::where('id', $news->community_id)->where('status', 1)->get();

    if ($communities->isEmpty()) {
        \Log::warning("No active communities found for News ID {$news->id}");
        return;
    }

    $fileFullPath = $news->image ? public_path($news->image) : null;
    $sentCount = 0;

    foreach ($communities as $community) {

        if (empty($community->discord_webhook_news)) {
            \Log::warning("Skipping community (no webhook): {$community->name}");
            continue;
        }

        // Check if Discord message already exists for this news & community
        $discordMsg = NewsDiscord::where('news_id', $news->id)
                                  ->where('community_id', $community->id)
                                  ->first();

        try {
            if ($discordMsg && $discordMsg->message_id) {
                // EDIT: existing message → PATCH content only
                Http::patch($community->discord_webhook_news . "/messages/{$discordMsg->message_id}", [
                    'content' => $news->content,
                ]);
            } else {
                // NEW message → attach image if exists
                if ($fileFullPath && file_exists($fileFullPath)) {
                    $response = Http::attach(
                        'file',
                        file_get_contents($fileFullPath),
                        basename($fileFullPath)
                    )->post($community->discord_webhook_news . '?wait=true', [
                        'content' => $news->content,
                    ]);
                } else {
                    $response = Http::post($community->discord_webhook_news . '?wait=true', [
                        'content' => $news->content,
                    ]);
                }

                // Save Discord message info
                if (isset($response) && $response->successful()) {
                    $discordData = $response->json();

                    NewsDiscord::updateOrCreate(
                        ['news_id' => $news->id, 'community_id' => $community->id],
                        [
                            'community'  => $community->name,
                            'message_id' => $discordData['id'] ?? null,
                            'channel_id' => $discordData['channel_id'] ?? null,
                        ]
                    );
                }
            }

            $sentCount++;
        } catch (\Exception $e) {
            \Log::error("Discord send/update failed for News ID {$news->id}, Community {$community->name}", [
                'error' => $e->getMessage()
            ]);
        }
    }

    if ($sentCount > 0) {
        $news->update(['discord_sent' => true]);
    }
}
}
