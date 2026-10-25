<?php

namespace Tests\Feature;

use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\Product;
use App\Models\Cart;
use App\Models\User;
use App\Support\StorefrontHtml;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StorefrontRedesignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The legacy storefront tables predate the repository's migrations.
        if (!Schema::hasTable('blog_categories')) {
            Schema::create('blog_categories', function (Blueprint $table) {
                $table->id();
                $table->string('blog_category')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
        if (!Schema::hasTable('blogs')) {
            Schema::create('blogs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('blog_category_id')->nullable();
                $table->string('blog_title')->nullable();
                $table->text('blog_description')->nullable();
                $table->string('blog_image')->nullable();
                $table->unsignedInteger('page_views')->default(0);
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('product')) {
            Schema::create('product', function (Blueprint $table) {
                $table->id();
                $table->string('product_name')->nullable();
                $table->integer('product_stock')->default(0);
                $table->decimal('product_price', 8, 2)->nullable();
                $table->string('product_image')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
        if (!Schema::hasTable('carts')) {
            Schema::create('carts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('product_id')->nullable();
                $table->string('guest_id')->nullable();
                $table->integer('quantity')->default(1);
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('footers')) {
            Schema::create('footers', function (Blueprint $table) {
                $table->id();
                $table->string('email')->nullable();
                $table->string('number')->nullable();
                $table->text('address')->nullable();
                $table->text('short_description')->nullable();
                $table->string('copyright')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_blog_search_and_category_filter_preserve_real_results(): void
    {
        $category = BlogCategory::create(['blog_category' => 'Insights']);
        $matching = Blog::create([
            'blog_category_id' => $category->id,
            'blog_title' => 'A trading journal',
            'blog_description' => '<p>Useful notes</p>',
        ]);
        Blog::create([
            'blog_category_id' => $category->id,
            'blog_title' => 'Other article',
            'blog_description' => '<p>Different subject</p>',
        ]);

        $this->get(route('category.blog', ['id' => $category->id, 'q' => 'trading']))
            ->assertOk()
            ->assertSee($matching->blog_title)
            ->assertDontSee('Other article');
        $this->get(route('blog.details', $matching->id))
            ->assertOk()
            ->assertSee($matching->blog_title);
    }

    public function test_guest_cart_shows_the_item_just_added(): void
    {
        $product = Product::create([
            'product_name' => 'Test controller',
            'product_stock' => 3,
            'product_price' => 49.00,
        ]);

        $this->post(route('guest.cart.add'), ['product_id' => $product->id, 'quantity' => 2])
            ->assertRedirect();
        $this->get(route('guest.cart.summary'))
            ->assertOk()
            ->assertSee('Test controller')
            ->assertSee('RM 98.00');

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('cart.summary'))->assertOk();
        $this->assertSame(2, Cart::where('user_id', $user->id)->sum('quantity'));
    }

    public function test_cms_html_removes_active_content(): void
    {
        $clean = StorefrontHtml::clean('<script>alert(1)</script><p onclick="alert(1)">Safe <a href="javascript:alert(1)">text</a></p>');
        $this->assertStringContainsString('<p>Safe', $clean);
        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
    }
}
