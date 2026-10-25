<?php

namespace Tests\Feature;

use App\Models\TradingBlog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TradingBlogWebPublicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_singapore_admin_publish_time_is_stored_as_utc_and_visible_to_mobile_readers(): void
    {
        $previousTimezone = date_default_timezone_get();
        date_default_timezone_set('UTC');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-09 08:00:00', 'UTC'));

        try {
            config()->set('app.timezone', 'UTC');
            config()->set('trading_blogs.publication_timezone', 'Asia/Singapore');
            $admin = User::factory()->create(['role_id' => 1, 'status' => 1]);

            $this->actingAs($admin)->post('/admin/trading-blogs', [
                'title' => 'Singapore Session Review',
                'category' => 'trading_sharing',
                'excerpt' => 'A review published from the Singapore admin dashboard.',
                'content' => '<p>Review the session structure.</p>',
                'status' => TradingBlog::STATUS_PUBLISHED,
                'published_at' => '2026-10-09T15:50',
            ])->assertRedirect(route('admin.trading.blogs.index'));

            $blog = TradingBlog::query()->sole();
            $this->assertSame('2026-10-09 07:50:00', $blog->published_at->format('Y-m-d H:i:s'));

            $this->actingAs($admin)
                ->get(route('admin.trading.blogs.edit', $blog))
                ->assertOk()
                ->assertSee('value="2026-10-09T15:50"', false);

            Sanctum::actingAs(User::factory()->create(['role_id' => 750, 'status' => 1]));
            $this->getJson('/api/mobile/v1/blogs')
                ->assertOk()
                ->assertJsonPath('data.blogs.0.id', (string) $blog->id);
        } finally {
            CarbonImmutable::setTestNow();
            date_default_timezone_set($previousTimezone);
        }
    }

    public function test_website_edit_and_category_change_are_returned_by_mobile_api(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'status' => 1]);
        $reader = User::factory()->create(['role_id' => 750, 'status' => 1]);
        $blog = TradingBlog::create([
            'author_id' => $admin->id,
            'title' => 'Original title',
            'slug' => 'original-title',
            'category' => 'trading_sharing',
            'excerpt' => 'Original excerpt.',
            'content' => '<p>Original content.</p>',
            'status' => TradingBlog::STATUS_PUBLISHED,
            'published_at' => now()->subMinute(),
            'source' => 'WEBSITE',
        ]);

        $this->actingAs($admin)->put('/admin/trading-blogs/'.$blog->id, [
            'title' => 'Updated risk controls',
            'category' => 'risk_management',
            'excerpt' => 'Updated excerpt.',
            'content' => '<p>Updated content from the website CMS.</p>',
            'tags' => 'risk, discipline',
            'status' => TradingBlog::STATUS_PUBLISHED,
            'published_at' => now(config('trading_blogs.publication_timezone'))->format('Y-m-d\TH:i'),
        ])->assertRedirect(route('admin.trading.blogs.index'));

        Sanctum::actingAs($reader);
        $this->getJson('/api/mobile/v1/blogs')
            ->assertOk()
            ->assertJsonPath('data.blogs.0.id', (string) $blog->id)
            ->assertJsonPath('data.blogs.0.title', 'Updated risk controls')
            ->assertJsonPath('data.blogs.0.category', 'risk_management')
            ->assertJsonPath('data.blogs.0.content', '<p>Updated content from the website CMS.</p>')
            ->assertJsonPath('data.blogs.0.version', 1);
    }
}
