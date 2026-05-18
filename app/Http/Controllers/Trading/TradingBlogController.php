<?php

namespace App\Http\Controllers\Trading;

use App\Http\Controllers\Controller;
use App\Models\TradingBlog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TradingBlogController extends Controller
{
    public function adminIndex(Request $request)
    {
        $this->ensureAdmin();

        $query = TradingBlog::with('author')->latest('updated_at');

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%")
                    ->orWhere('tags', 'like', "%{$search}%");
            });
        }

        $blogs = $query->paginate(15)->withQueryString();
        $categories = TradingBlog::categories();
        $statuses = TradingBlog::statuses();
        $totalBlogs = TradingBlog::count();
        $publishedBlogs = TradingBlog::where('status', TradingBlog::STATUS_PUBLISHED)->count();
        $draftBlogs = TradingBlog::where('status', TradingBlog::STATUS_DRAFT)->count();

        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Trading Blog', 'url' => route('admin.trading.blogs.index')],
        ];

        return view('admin.trading_blogs.index', compact(
            'blogs',
            'categories',
            'statuses',
            'totalBlogs',
            'publishedBlogs',
            'draftBlogs',
            'breadcrumbData'
        ));
    }

    public function create()
    {
        $this->ensureAdmin();

        $categories = TradingBlog::categories();
        $statuses = TradingBlog::statuses();

        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Trading Blog', 'url' => route('admin.trading.blogs.index')],
            ['label' => 'Add Post', 'url' => route('admin.trading.blogs.create')],
        ];

        return view('admin.trading_blogs.create', compact('categories', 'statuses', 'breadcrumbData'));
    }

    public function store(Request $request)
    {
        $this->ensureAdmin();

        $data = $this->validatedBlogData($request);
        $data['slug'] = $this->uniqueSlug($data['title']);
        $data['author_id'] = auth()->id();
        $data['cover_image'] = $this->uploadCoverImage($request);
        $data['excerpt'] = $data['excerpt'] ?: Str::words(strip_tags($data['content']), 30);
        $data = $this->normalizePublishingData($data);

        TradingBlog::create($data);

        return redirect()
            ->route('admin.trading.blogs.index')
            ->with('success', 'Trading blog post created successfully.');
    }

    public function edit(TradingBlog $blog)
    {
        $this->ensureAdmin();

        $categories = TradingBlog::categories();
        $statuses = TradingBlog::statuses();

        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Trading Blog', 'url' => route('admin.trading.blogs.index')],
            ['label' => 'Edit Post', 'url' => route('admin.trading.blogs.edit', $blog->id)],
        ];

        return view('admin.trading_blogs.edit', compact('blog', 'categories', 'statuses', 'breadcrumbData'));
    }

    public function update(Request $request, TradingBlog $blog)
    {
        $this->ensureAdmin();

        $data = $this->validatedBlogData($request);
        $data['slug'] = $this->uniqueSlug($data['title'], $blog->id);
        $data['excerpt'] = $data['excerpt'] ?: Str::words(strip_tags($data['content']), 30);
        $data = $this->normalizePublishingData($data, $blog);

        if ($request->hasFile('cover_image')) {
            $this->deleteCoverImage($blog->cover_image);
            $data['cover_image'] = $this->uploadCoverImage($request);
        }

        $blog->update($data);

        return redirect()
            ->route('admin.trading.blogs.index')
            ->with('success', 'Trading blog post updated successfully.');
    }

    public function destroy(TradingBlog $blog)
    {
        $this->ensureAdmin();

        $this->deleteCoverImage($blog->cover_image);
        $blog->delete();

        return redirect()
            ->route('admin.trading.blogs.index')
            ->with('success', 'Trading blog post deleted successfully.');
    }

    public function index(Request $request)
    {
        $this->ensureTradingReader();

        $categories = TradingBlog::categories();
        $activeCategory = $request->input('category');
        $search = trim((string) $request->input('search'));

        $query = TradingBlog::with('author')->published()->latest('published_at');

        if ($activeCategory && array_key_exists($activeCategory, $categories)) {
            $query->where('category', $activeCategory);
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%")
                    ->orWhere('tags', 'like', "%{$search}%");
            });
        }

        $featuredBlog = TradingBlog::with('author')
            ->published()
            ->where('is_featured', true)
            ->latest('published_at')
            ->first();

        $blogs = $query
            ->when($featuredBlog, fn ($builder) => $builder->where('id', '!=', $featuredBlog->id))
            ->paginate(9)
            ->withQueryString();

        $categoryCounts = TradingBlog::published()
            ->selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Trading Blog', 'url' => route('trading.blogs.index')],
        ];

        return view('traders.trading_blogs.index', compact(
            'blogs',
            'featuredBlog',
            'categories',
            'categoryCounts',
            'activeCategory',
            'search',
            'breadcrumbData'
        ));
    }

    public function show(TradingBlog $blog)
    {
        $this->ensureTradingReader();
        abort_unless($blog->status === TradingBlog::STATUS_PUBLISHED && $blog->published_at && $blog->published_at->lte(now()), 404);

        $blog->increment('views');

        $relatedBlogs = TradingBlog::published()
            ->where('id', '!=', $blog->id)
            ->where('category', $blog->category)
            ->latest('published_at')
            ->take(3)
            ->get();

        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Trading Blog', 'url' => route('trading.blogs.index')],
            ['label' => $blog->title, 'url' => route('trading.blogs.show', $blog->slug)],
        ];

        return view('traders.trading_blogs.show', compact('blog', 'relatedBlogs', 'breadcrumbData'));
    }

    private function validatedBlogData(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(array_keys(TradingBlog::categories()))],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'content' => ['required', 'string'],
            'cover_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
            'tags' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in(array_keys(TradingBlog::statuses()))],
            'is_featured' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ]);
    }

    private function normalizePublishingData(array $data, ?TradingBlog $blog = null): array
    {
        $data['is_featured'] = filter_var($data['is_featured'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($data['status'] === TradingBlog::STATUS_PUBLISHED) {
            $data['published_at'] = $data['published_at'] ?? optional($blog)->published_at ?? now();
        } else {
            $data['published_at'] = null;
            $data['is_featured'] = false;
        }

        return $data;
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($title) ?: 'trading-post';
        $slug = $baseSlug;
        $counter = 2;

        while (
            TradingBlog::where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function uploadCoverImage(Request $request): ?string
    {
        if (! $request->hasFile('cover_image')) {
            return null;
        }

        $image = $request->file('cover_image');
        $fileName = uniqid('trading_blog_', true) . '.' . $image->getClientOriginalExtension();
        $destination = public_path('upload/trading_blogs');

        if (! File::exists($destination)) {
            File::makeDirectory($destination, 0755, true);
        }

        $image->move($destination, $fileName);

        return 'upload/trading_blogs/' . $fileName;
    }

    private function deleteCoverImage(?string $path): void
    {
        if ($path && File::exists(public_path($path))) {
            File::delete(public_path($path));
        }
    }

    private function ensureAdmin(): void
    {
        abort_unless(auth()->check() && in_array((int) auth()->user()->role_id, [1, 2], true), 403);
    }

    private function ensureTradingReader(): void
    {
        abort_unless(auth()->check() && in_array((int) auth()->user()->role_id, [1, 2, 750, 760, 770], true), 403);
    }
}
