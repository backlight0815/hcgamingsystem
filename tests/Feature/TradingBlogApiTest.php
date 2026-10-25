<?php

namespace Tests\Feature;

use App\Models\TradingBlog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TradingBlogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_reader_receives_published_website_blogs(): void
    {
        $reader = $this->user(750);
        $author = $this->user(1);
        $published = $this->blog(['author_id' => $author->id]);
        $this->blog(['status' => TradingBlog::STATUS_DRAFT, 'published_at' => null]);
        Sanctum::actingAs($reader);

        $this->getJson('/api/mobile/v1/blogs')
            ->assertOk()
            ->assertJsonPath('data.blogs.0.id', (string) $published->id)
            ->assertJsonPath('data.blogs.0.source', 'WEBSITE')
            ->assertJsonPath('data.blogs.0.title', 'Price Action Basics')
            ->assertJsonPath('data.blogs.0.title_zh', '')
            ->assertJsonPath('data.blogs.0.excerpt_zh', '')
            ->assertJsonPath('data.blogs.0.content_zh', '')
            ->assertJsonPath('data.blogs.0.tags.0', 'price action')
            ->assertJsonPath('data.blogs.0.author.id', (string) $author->id)
            ->assertJsonPath('data.blogs.0.author.name', $author->name)
            ->assertJsonPath('data.blogs.0.reading_minutes', 1)
            ->assertJsonCount(1, 'data.blogs');
    }

    public function test_opening_blog_detail_records_view_and_returns_complete_payload(): void
    {
        $author = $this->user(1);
        $reader = $this->user(750);
        $blog = $this->blog([
            'author_id' => $author->id,
            'views' => 7,
            'cover_image' => 'upload/trading_blogs/cover.webp',
        ]);
        Sanctum::actingAs($reader);

        $this->getJson('/api/mobile/v1/blogs/'.$blog->id)
            ->assertOk()
            ->assertJsonPath('data.blog.views', 8)
            ->assertJsonPath('data.blog.author.id', (string) $author->id)
            ->assertJsonPath('data.blog.source', 'WEBSITE')
            ->assertJsonPath('data.blog.version', 0)
            ->assertJsonPath('data.blog.reading_minutes', 1)
            ->assertJsonPath('data.blog.cover_image', asset('upload/trading_blogs/cover.webp'));

        $this->assertSame(8, $blog->fresh()->views);
    }

    public function test_mobile_admin_publishes_english_blog_to_shared_database(): void
    {
        $admin = $this->user(1);
        Sanctum::actingAs($admin);

        $this->postJson('/api/mobile/v1/blogs', $this->payload(), [
            'X-Idempotency-Key' => 'blog-device-request-1',
        ])->assertCreated()
            ->assertJsonPath('data.blog.status', 'published')
            ->assertJsonPath('data.blog.source', 'MOBILE_APP')
            ->assertJsonPath('data.blog.title', 'Price Action Basics')
            ->assertJsonPath('data.blog.title_zh', '')
            ->assertJsonPath('data.blog.excerpt_zh', '')
            ->assertJsonPath('data.blog.content_zh', '')
            ->assertJsonPath('data.idempotent_replay', false);

        $this->assertDatabaseHas('trading_blogs', [
            'author_id' => $admin->id,
            'title' => 'Price Action Basics',
            'source' => 'MOBILE_APP',
            'client_request_id' => 'blog-device-request-1',
            'status' => TradingBlog::STATUS_PUBLISHED,
        ]);
    }

    public function test_mobile_blog_submission_is_idempotent(): void
    {
        Sanctum::actingAs($this->user(1));
        $headers = ['X-Idempotency-Key' => 'same-blog-request'];

        $this->postJson('/api/mobile/v1/blogs', $this->payload(), $headers)->assertCreated();
        $this->postJson('/api/mobile/v1/blogs', $this->payload(), $headers)
            ->assertOk()
            ->assertJsonPath('data.idempotent_replay', true);

        $this->assertDatabaseCount('trading_blogs', 1);
    }

    public function test_reusing_blog_idempotency_key_for_different_content_conflicts(): void
    {
        Sanctum::actingAs($this->user(1));
        $headers = ['X-Idempotency-Key' => 'conflicting-blog-request'];
        $this->postJson('/api/mobile/v1/blogs', $this->payload(), $headers)->assertCreated();

        $changed = $this->payload();
        $changed['title'] = 'A Different Article';
        $this->postJson('/api/mobile/v1/blogs', $changed, $headers)
            ->assertConflict()
            ->assertJsonPath('success', false);
    }

    public function test_non_publisher_cannot_create_a_blog(): void
    {
        Sanctum::actingAs($this->user(750));

        $this->postJson('/api/mobile/v1/blogs', $this->payload())->assertForbidden();
    }

    public function test_public_blog_detail_never_exposes_a_draft(): void
    {
        $author = $this->user(1);
        $draft = $this->blog([
            'author_id' => $author->id,
            'status' => TradingBlog::STATUS_DRAFT,
            'published_at' => null,
        ]);

        Sanctum::actingAs($author);
        $this->getJson('/api/mobile/v1/blogs/'.$draft->id)->assertNotFound();
        $this->getJson('/api/mobile/v1/blogs')->assertOk()->assertJsonCount(0, 'data.blogs');
    }

    public function test_optimistic_version_prevents_stale_blog_update(): void
    {
        $admin = $this->user(1);
        $blog = $this->blog(['author_id' => $admin->id, 'version' => 2]);
        Sanctum::actingAs($admin);

        $this->putJson('/api/mobile/v1/blogs/'.$blog->id, [
            'title' => 'Stale title',
            'expected_version' => 1,
        ])->assertConflict();

        $this->putJson('/api/mobile/v1/blogs/'.$blog->id, [
            'title' => 'Current title',
            'expected_version' => 2,
        ])->assertOk()
            ->assertJsonPath('data.blog.version', 3)
            ->assertJsonPath('data.blog.title', 'Current title');
    }

    public function test_admin_can_upload_blog_cover_image(): void
    {
        Sanctum::actingAs($this->user(1));
        $directory = public_path('upload/trading_blogs');

        try {
            $response = $this->post('/api/mobile/v1/blogs/images', [
                'image' => UploadedFile::fake()->image('cover.jpg', 800, 400),
            ], ['Accept' => 'application/json']);

            $response->assertCreated()->assertJsonPath('success', true);
            $path = $response->json('data.path');
            $this->assertIsString($path);
            $this->assertFileExists(public_path($path));
        } finally {
            if (isset($path) && File::exists(public_path($path))) {
                File::delete(public_path($path));
            }
            if (File::isDirectory($directory) && count(File::files($directory)) === 0) {
                File::deleteDirectory($directory);
            }
        }
    }

    public function test_published_blog_reads_need_no_login_but_writes_still_do(): void
    {
        config()->set('trading_signals.firebase_web_api_key', null);
        $published = $this->blog(['author_id' => $this->user(1)->id]);
        $draft = $this->blog(['status' => TradingBlog::STATUS_DRAFT, 'published_at' => null]);

        $this->getJson('/api/mobile/v1/blogs')
            ->assertOk()
            ->assertJsonCount(1, 'data.blogs')
            ->assertJsonPath('data.blogs.0.id', (string) $published->id);
        $this->getJson('/api/mobile/v1/blogs/'.$published->id)->assertOk();
        $this->getJson('/api/mobile/v1/blogs/'.$draft->id)->assertNotFound();
        $this->postJson('/api/mobile/v1/blogs', $this->payload())
            ->assertUnauthorized();
    }

    public function test_category_filter_search_content_and_counts_use_published_posts(): void
    {
        Sanctum::actingAs($this->user(750));
        $match = $this->blog([
            'category' => 'risk_management',
            'title' => 'Protect the account',
            'excerpt' => 'Position sizing principles.',
            'content' => '<p>Wait for a liquidity sweep before reducing exposure.</p>',
            'tags' => 'risk, sizing',
        ]);
        $this->blog([
            'category' => 'knowledge_sharing',
            'title' => 'Candlestick notes',
        ]);
        $this->blog([
            'category' => 'risk_management',
            'status' => TradingBlog::STATUS_DRAFT,
            'published_at' => null,
        ]);

        $this->getJson('/api/mobile/v1/blogs?category=risk_management&search=liquidity')
            ->assertOk()
            ->assertJsonCount(1, 'data.blogs')
            ->assertJsonPath('data.blogs.0.id', (string) $match->id)
            ->assertJsonPath('data.category_counts.risk_management', 1)
            ->assertJsonPath('data.category_counts.knowledge_sharing', 1)
            ->assertJsonPath('data.category_counts.market_outlook', 0)
            ->assertJsonPath('data.category_options.4.id', 'risk_management')
            ->assertJsonPath('data.category_options.4.published_count', 1);
    }

    public function test_future_publication_is_hidden_until_its_utc_time_arrives(): void
    {
        Sanctum::actingAs($this->user(750));
        $this->blog(['published_at' => now()->addMinute()]);

        $this->getJson('/api/mobile/v1/blogs')
            ->assertOk()
            ->assertJsonCount(0, 'data.blogs');
    }

    public function test_missing_optional_text_is_serialized_as_empty_strings(): void
    {
        Sanctum::actingAs($this->user(750));
        $this->blog([
            'excerpt' => null,
            'cover_image' => null,
            'tags' => null,
        ]);

        $this->getJson('/api/mobile/v1/blogs')
            ->assertOk()
            ->assertJsonPath('data.blogs.0.excerpt', '')
            ->assertJsonPath('data.blogs.0.cover_image', '')
            ->assertJsonPath('data.blogs.0.title_zh', '')
            ->assertJsonPath('data.blogs.0.excerpt_zh', '')
            ->assertJsonPath('data.blogs.0.content_zh', '')
            ->assertJsonPath('data.blogs.0.tags', []);
    }

    public function test_device_language_parameter_does_not_change_english_content(): void
    {
        Sanctum::actingAs($this->user(750));
        $this->blog();

        $this->getJson('/api/mobile/v1/blogs?locale=zh-CN')
            ->assertOk()
            ->assertJsonPath('data.blogs.0.title', 'Price Action Basics')
            ->assertJsonPath('data.blogs.0.content', '<p>Read structure first.</p>')
            ->assertJsonPath('data.blogs.0.title_zh', '');
    }

    public function test_pagination_and_invalid_query_contracts_are_predictable(): void
    {
        Sanctum::actingAs($this->user(750));
        $this->blog(['published_at' => now()->subMinutes(3)]);
        $this->blog(['published_at' => now()->subMinutes(2)]);
        $this->blog(['published_at' => now()->subMinute()]);

        $this->getJson('/api/mobile/v1/blogs?per_page=2&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data.blogs')
            ->assertJsonPath('data.pagination.current_page', 2)
            ->assertJsonPath('data.pagination.last_page', 2)
            ->assertJsonPath('data.pagination.per_page', 2)
            ->assertJsonPath('data.pagination.total', 3);

        $this->getJson('/api/mobile/v1/blogs?category=not-a-category')->assertUnprocessable();
        $this->getJson('/api/mobile/v1/blogs/999999')->assertNotFound();
    }

    public function test_unpublished_and_deleted_posts_disappear_from_reader_api(): void
    {
        $admin = $this->user(1);
        $reader = $this->user(750);
        $blog = $this->blog(['author_id' => $admin->id]);

        Sanctum::actingAs($admin);
        $this->postJson('/api/mobile/v1/blogs/'.$blog->id.'/unpublish', [
            'expected_version' => 0,
        ])->assertOk()
            ->assertJsonPath('data.blog.status', TradingBlog::STATUS_DRAFT);

        Sanctum::actingAs($reader);
        $this->getJson('/api/mobile/v1/blogs')->assertOk()->assertJsonCount(0, 'data.blogs');

        Sanctum::actingAs($admin);
        $this->deleteJson('/api/mobile/v1/blogs/'.$blog->id)->assertOk();
        $this->assertDatabaseMissing('trading_blogs', ['id' => $blog->id]);
    }

    private function user(int $roleId): User
    {
        return User::factory()->create(['role_id' => $roleId, 'status' => 1]);
    }

    private function payload(): array
    {
        return [
            'title' => 'Price Action Basics',
            'category' => 'knowledge_sharing',
            'excerpt' => 'A concise introduction to reading price action.',
            'content' => '<p>Read structure before planning an entry.</p>',
            'tags' => ['price action', 'structure'],
            'status' => TradingBlog::STATUS_PUBLISHED,
            'is_featured' => false,
        ];
    }

    private function blog(array $overrides = []): TradingBlog
    {
        return TradingBlog::create(array_merge([
            'title' => 'Price Action Basics',
            'slug' => 'price-action-basics-'.uniqid(),
            'category' => 'knowledge_sharing',
            'excerpt' => 'A concise introduction.',
            'content' => '<p>Read structure first.</p>',
            'tags' => 'price action, structure',
            'status' => TradingBlog::STATUS_PUBLISHED,
            'published_at' => now(),
            'source' => 'WEBSITE',
        ], $overrides));
    }
}
