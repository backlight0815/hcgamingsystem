<?php

namespace App\Console\Commands;

use App\Models\TradingBlog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SeedTradingBlogTestData extends Command
{
    protected $signature = 'trading:seed-blog-test-data
        {author_id=1 : User ID to assign as the blog author}
        {--count=10 : Number of blog posts to generate}
        {--fresh : Remove previously generated test blog posts before creating new rows}';

    protected $description = 'Generate trading blog test posts for development and QA.';

    private const MARKER = 'TEST-SEED-TRADING-BLOG';

    public function handle(): int
    {
        $authorId = (int) $this->argument('author_id');
        $count = max(1, min(50, (int) $this->option('count')));

        $author = User::find($authorId);

        if (!$author) {
            $this->error("Author user ID {$authorId} was not found.");

            return self::FAILURE;
        }

        $templates = $this->templates();

        DB::transaction(function () use ($authorId, $count, $templates) {
            if ($this->option('fresh')) {
                TradingBlog::query()
                    ->where('tags', 'like', '%'.self::MARKER.'%')
                    ->delete();
            }

            for ($index = 0; $index < $count; $index++) {
                $template = $templates[$index % count($templates)];
                $number = $index + 1;
                $title = $number > count($templates)
                    ? $template['title'].' Part '.ceil($number / count($templates))
                    : $template['title'];

                TradingBlog::create([
                    'author_id' => $authorId,
                    'title' => $title,
                    'slug' => $this->uniqueSlug($title),
                    'category' => $template['category'],
                    'excerpt' => $template['excerpt'],
                    'content' => $this->content($template, $number),
                    'cover_image' => null,
                    'tags' => implode(', ', array_merge($template['tags'], [self::MARKER])),
                    'status' => TradingBlog::STATUS_PUBLISHED,
                    'is_featured' => $index === 0,
                    'published_at' => Carbon::today()->subDays($count - $number)->setTime(9 + ($index % 5), 30),
                    'views' => 120 + ($number * 37),
                ]);
            }
        });

        $generatedCount = TradingBlog::query()
            ->where('tags', 'like', '%'.self::MARKER.'%')
            ->count();

        $this->info("Generated {$count} trading blog posts for author #{$authorId} ({$author->username}).");
        $this->line("Total generated test blog posts now found: {$generatedCount}.");

        return self::SUCCESS;
    }

    private function uniqueSlug(string $title): string
    {
        $baseSlug = Str::slug($title) ?: 'trading-blog-post';
        $slug = $baseSlug;
        $counter = 2;

        while (TradingBlog::where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private function templates(): array
    {
        return [
            [
                'title' => 'How To Build A Trading Plan Before Entering XAUUSD',
                'category' => 'trading_sharing',
                'excerpt' => 'A practical walkthrough for preparing direction, invalidation, risk, and target zones before entering a gold trade.',
                'tags' => ['xauusd', 'trading plan', 'execution'],
            ],
            [
                'title' => 'Risk Management Rules Every Funded Trader Should Respect',
                'category' => 'risk_management',
                'excerpt' => 'Simple rules for protecting daily drawdown, sizing positions, and avoiding emotional recovery trades.',
                'tags' => ['risk management', 'funded trader', 'drawdown'],
            ],
            [
                'title' => 'Trading Psychology: Handling A Losing Streak Professionally',
                'category' => 'psychology_sharing',
                'excerpt' => 'A calm framework for reviewing losses, reducing size, and returning to high quality setups after a difficult sequence.',
                'tags' => ['psychology', 'discipline', 'loss review'],
            ],
            [
                'title' => 'Market Outlook: Reading The Week With Support And Resistance',
                'category' => 'market_outlook',
                'excerpt' => 'How to organize weekly market context using higher timeframe structure, liquidity zones, and session behavior.',
                'tags' => ['market outlook', 'support resistance', 'weekly plan'],
            ],
            [
                'title' => 'Knowledge Sharing: Why Entry Confirmation Matters',
                'category' => 'knowledge_sharing',
                'excerpt' => 'A beginner friendly explanation of confirmation, trigger timing, and why patience improves trade quality.',
                'tags' => ['knowledge', 'confirmation', 'entry'],
            ],
            [
                'title' => 'Future Prop Firm Preparation Checklist',
                'category' => 'future_prop_firm_sharing',
                'excerpt' => 'A checklist for traders preparing for evaluation phases, consistency rules, and account protection requirements.',
                'tags' => ['prop firm', 'evaluation', 'checklist'],
            ],
            [
                'title' => 'Funded Trader Journey: From Consistency To Scale',
                'category' => 'funded_trader_journey',
                'excerpt' => 'Lessons from building consistency, tracking performance, and preparing a trader profile for account scaling.',
                'tags' => ['funded trader', 'journey', 'scale'],
            ],
            [
                'title' => 'How To Review A Trade Journal Like A Professional',
                'category' => 'trading_sharing',
                'excerpt' => 'A practical method for reviewing win rate, average RRR, expectancy, and repeated mistakes in your trading journal.',
                'tags' => ['journal', 'performance', 'review'],
            ],
            [
                'title' => 'Break Even Management Without Killing Good Trades',
                'category' => 'risk_management',
                'excerpt' => 'When to move stop loss to break even, when to leave space, and how to avoid protecting a trade too early.',
                'tags' => ['break even', 'stop loss', 'trade management'],
            ],
            [
                'title' => 'Senior Signal Provider Habits That Improve Trust',
                'category' => 'knowledge_sharing',
                'excerpt' => 'Clear signal structure, honest invalidation, proper follow-up, and transparent performance reporting for providers.',
                'tags' => ['signal provider', 'trust', 'reporting'],
            ],
        ];
    }

    private function content(array $template, int $number): string
    {
        $title = e($template['title']);
        $category = e(TradingBlog::categories()[$template['category']] ?? 'Trading');

        return <<<HTML
<p><strong>{$category}</strong> test article #{$number}. This post is generated for development, dashboard QA, blog filtering, and reader layout testing.</p>

<h3>Core Idea</h3>
<p>{$title} starts with a simple rule: define the trading decision before the market pressure begins. A trader should know the setup, invalidation point, risk amount, and expected target before execution.</p>

<h3>Practical Checklist</h3>
<ul>
    <li>Confirm the market structure and key support or resistance area.</li>
    <li>Write down the entry condition before taking the trade.</li>
    <li>Keep risk per trade consistent with account rules.</li>
    <li>Record the result in the trading journal after closure.</li>
</ul>

<h3>Administration Note</h3>
<p>For platform testing, this article includes enough text to validate excerpts, reading time, category filters, featured post behavior, search, related posts, and published blog visibility.</p>
HTML;
    }
}
