<?php

namespace App\Http\Controllers\Trading;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\News;
use App\Models\NewsDiscord;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class NewsController extends Controller
{
    public function index()
    {
        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'News', 'url' => route('trading.news.index')],
        ];

        $news = News::with(['community', 'discordMessages'])
            ->orderBy('news_date', 'desc')
            ->latest('id')
            ->get();

        $metrics = [
            'total' => $news->count(),
            'high' => $news->where('impact', 3)->count(),
            'medium' => $news->where('impact', 2)->count(),
            'low' => $news->where('impact', 1)->count(),
            'discord' => $news->filter(fn (News $item): bool => $item->discordMessages->isNotEmpty())->count(),
        ];

        $totalNews = $metrics['total'];

        return view('admin.forex_news.news_all', compact('news', 'breadcrumbData', 'totalNews', 'metrics'));
    }

    public function create()
    {
        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'News', 'url' => route('trading.news.index')],
            ['label' => 'Add News', 'url' => route('trading.news.create')],
        ];

        $communities = Community::where('status', 1)->orderBy('name')->get();
        $news = new News([
            'news_date' => now()->toDateString(),
            'impact' => 2,
        ]);

        return view('admin.forex_news.news_add', compact('breadcrumbData', 'communities', 'news'));
    }

    public function store(Request $request)
    {
        $data = $this->validatedNews($request);
        $imagePath = $this->storeImage($request);

        $news = News::create([
            'content' => $this->buildNewsContent((int) $data['impact'], $data['news_date']),
            'impact' => (int) $data['impact'],
            'news_date' => $data['news_date'],
            'image' => $imagePath,
            'community_id' => $data['community_id'],
        ]);

        if (feature_enabled('DiscordIntegration')) {
            $this->sendToDiscord($news);
        }

        return redirect()->route('trading.news.index')->with('success', 'Trading news briefing created successfully.');
    }

    public function show($id)
    {
        $news = News::with(['community', 'discordMessages.community'])->findOrFail($id);

        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'News', 'url' => route('trading.news.index')],
            ['label' => 'News Details', 'url' => route('trading.news.show', $news->id)],
        ];

        return view('admin.forex_news.news_show', compact('news', 'breadcrumbData'));
    }

    public function edit($id)
    {
        $news = News::findOrFail($id);
        $communities = Community::where('status', 1)->orderBy('name')->get();

        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'News', 'url' => route('trading.news.index')],
            ['label' => 'Edit News', 'url' => route('trading.news.edit', $news->id)],
        ];

        return view('admin.forex_news.news_edit', compact('news', 'breadcrumbData', 'communities'));
    }

    public function update(Request $request, $id)
    {
        $news = News::findOrFail($id);
        $data = $this->validatedNews($request);

        if ($request->hasFile('image')) {
            $news->image = $this->storeImage($request, $news);
        }

        $news->impact = (int) $data['impact'];
        $news->news_date = $data['news_date'];
        $news->community_id = $data['community_id'];
        $news->content = $this->buildNewsContent((int) $data['impact'], $data['news_date']);
        $news->save();

        if (feature_enabled('DiscordIntegration')) {
            $this->sendToDiscord($news);
        }

        return redirect()->route('trading.news.show', $news->id)->with('success', 'Trading news briefing updated successfully.');
    }

    public function destroy($id)
    {
        $news = News::findOrFail($id);
        $this->deleteImage($news->image);
        $news->delete();

        return redirect()->route('trading.news.index')->with('success', 'Trading news briefing deleted successfully.');
    }

    public function sendToDiscord($news)
    {
        $manualRequest = ! $news instanceof News;
        $news = $news instanceof News ? $news : News::findOrFail($news);

        if (! feature_enabled('DiscordIntegration')) {
            return $manualRequest
                ? back()->with('error', 'Discord integration is currently disabled.')
                : null;
        }

        $communities = Community::where('id', $news->community_id)
            ->where('status', 1)
            ->get();

        if ($communities->isEmpty()) {
            return $manualRequest
                ? back()->with('error', 'No active community is linked to this news briefing.')
                : null;
        }

        $fileFullPath = $news->image ? public_path($news->image) : null;
        $sentCount = 0;

        foreach ($communities as $community) {
            if (blank($community->discord_webhook_news)) {
                continue;
            }

            $discordMsg = NewsDiscord::where('news_id', $news->id)
                ->where('community_id', $community->id)
                ->first();

            try {
                if ($discordMsg && $discordMsg->message_id) {
                    Http::patch($community->discord_webhook_news . "/messages/{$discordMsg->message_id}", [
                        'content' => $news->content,
                    ]);

                    $sentCount++;
                    continue;
                }

                $response = ($fileFullPath && file_exists($fileFullPath))
                    ? Http::attach('file', file_get_contents($fileFullPath), basename($fileFullPath))
                        ->post($community->discord_webhook_news . '?wait=true', ['content' => $news->content])
                    : Http::post($community->discord_webhook_news . '?wait=true', ['content' => $news->content]);

                if ($response->successful()) {
                    $discordData = $response->json();

                    NewsDiscord::updateOrCreate(
                        ['news_id' => $news->id, 'community_id' => $community->id],
                        [
                            'message_id' => $discordData['id'] ?? null,
                            'channel_id' => $discordData['channel_id'] ?? null,
                        ]
                    );

                    $sentCount++;
                }
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return $manualRequest
            ? back()->with($sentCount > 0 ? 'success' : 'error', $sentCount > 0 ? 'News briefing sent to Discord.' : 'No Discord message was sent. Please check the community webhook.')
            : null;
    }

    private function validatedNews(Request $request): array
    {
        return $request->validate([
            'news_date' => 'required|date',
            'impact' => 'required|integer|in:1,2,3',
            'community_id' => 'required|exists:communities,id',
            'image' => 'nullable|image|max:4096',
        ]);
    }

    private function storeImage(Request $request, ?News $existingNews = null): ?string
    {
        if (! $request->hasFile('image')) {
            return $existingNews?->image;
        }

        $image = $request->file('image');
        $imageName = now()->format('YmdHis') . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
        $destinationPath = public_path('upload/news');

        if (! file_exists($destinationPath)) {
            mkdir($destinationPath, 0755, true);
        }

        $image->move($destinationPath, $imageName);

        if ($existingNews) {
            $this->deleteImage($existingNews->image);
        }

        return 'upload/news/' . $imageName;
    }

    private function deleteImage(?string $path): void
    {
        if ($path && file_exists(public_path($path))) {
            unlink(public_path($path));
        }
    }

    private function buildNewsContent(int $impact, string $date): string
    {
        $profile = $this->impactProfile($impact);
        $newsDate = Carbon::parse($date)->format('D, d M Y');

        return implode(PHP_EOL, [
            'Market News Briefing',
            'Date: ' . $newsDate,
            'Impact Level: ' . $profile['label'],
            'Primary Focus: USD-related scheduled news',
            'Risk Guidance: ' . $profile['risk'],
            'Execution Plan: ' . $profile['execution'],
            'Reminder: Confirm your trading plan, position size, stop placement, and news risk before taking any trade.',
        ]);
    }

    private function impactProfile(int $impact): array
    {
        return match ($impact) {
            3 => [
                'label' => 'High Impact',
                'risk' => 'Expect fast repricing, wider spreads, and possible slippage around the release window.',
                'execution' => 'Reduce exposure, avoid impulsive entries, and wait for post-news structure before committing risk.',
            ],
            2 => [
                'label' => 'Medium Impact',
                'risk' => 'Expect moderate volatility and possible short-term liquidity changes.',
                'execution' => 'Use planned levels, keep stops logical, and avoid increasing size without confirmation.',
            ],
            default => [
                'label' => 'Low Impact',
                'risk' => 'Market movement is usually more contained, but risk controls still apply.',
                'execution' => 'Trade only if the setup matches the plan and the reward-to-risk remains acceptable.',
            ],
        };
    }
}
