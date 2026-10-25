<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Requests\Api\StoreTradingBlogRequest;
use App\Http\Requests\Api\UpdateTradingBlogRequest;
use App\Http\Resources\TradingBlogResource;
use App\Models\TradingBlog;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TradingBlogController extends MobileController
{
    public function index(Request $request): JsonResponse
    {
        $startedAt = microtime(true);
        $request->validate([
            'category' => ['nullable', 'string', Rule::in(array_keys(TradingBlog::categories()))],
            'status' => ['nullable', 'string', Rule::in(array_keys(TradingBlog::statuses()))],
            'search' => ['nullable', 'string', 'max:255'],
            'since' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $query = TradingBlog::query()
            ->with('author:id,name,username')
            ->published();
        $requestedStatus = $request->filled('status')
            ? strtolower($request->string('status')->toString())
            : null;
        if ($requestedStatus !== null && $requestedStatus !== TradingBlog::STATUS_PUBLISHED) {
            $query->whereRaw('1 = 0');
        }

        if ($request->filled('category')) {
            $query->where('category', $request->string('category')->toString());
        }
        if ($request->filled('search')) {
            $search = trim($request->string('search')->toString());
            $query->where(function ($query) use ($search): void {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%")
                    ->orWhere('tags', 'like', "%{$search}%");
            });
        }
        if ($request->filled('since')) {
            $query->where('updated_at', '>', $request->date('since'));
        }

        $paginator = $query
            ->orderByRaw('CASE WHEN published_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(min(max($request->integer('per_page', 30), 1), 100));

        $publishedCounts = TradingBlog::query()
            ->published()
            ->selectRaw('category, COUNT(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category');
        $categories = TradingBlog::categories();
        $categoryCounts = collect($categories)
            ->mapWithKeys(fn (string $label, string $key): array => [
                $key => (int) ($publishedCounts[$key] ?? 0),
            ])
            ->all();
        $categoryOptions = collect($categories)
            ->map(fn (string $label, string $key): array => [
                'id' => $key,
                'name' => $label,
                'published_count' => $categoryCounts[$key],
            ])
            ->values()
            ->all();

        Log::info('Trading blog API list completed.', [
            'user_id' => $request->user()?->id,
            'category' => $request->input('category'),
            'status_filter' => $request->input('status'),
            'has_search' => $request->filled('search'),
            'page' => $paginator->currentPage(),
            'returned_count' => $paginator->count(),
            'total_count' => $paginator->total(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return $this->success([
            'blogs' => TradingBlogResource::collection($paginator->items())->resolve($request),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            // Keep the original key => label map for existing clients while
            // exposing counts and a conventional list for newer clients.
            'categories' => $categories,
            'category_counts' => $categoryCounts,
            'category_options' => $categoryOptions,
            'synced_at' => now()->toIso8601String(),
        ], 'Trading blogs loaded.')
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function show(Request $request, TradingBlog $blog): JsonResponse
    {
        abort_unless($blog->status === TradingBlog::STATUS_PUBLISHED
            && $blog->published_at?->lte(now()), 404);
        $blog->load('author:id,name,username');
        TradingBlog::withoutTimestamps(fn () => $blog->increment('views'));

        return $this->success([
            'blog' => (new TradingBlogResource($blog->fresh('author')))->resolve($request),
        ], 'Trading blog loaded.');
    }

    public function store(StoreTradingBlogRequest $request): JsonResponse
    {
        $data = $request->validated();
        $requestId = trim((string) ($data['client_request_id'] ?? ''));
        if ($requestId !== '') {
            $existing = TradingBlog::query()
                ->where('author_id', $request->user()->id)
                ->where('client_request_id', $requestId)
                ->first();
            if ($existing) {
                return $this->idempotentResult($request, $existing, $data);
            }
        }

        try {
            $blog = DB::transaction(function () use ($request, $data, $requestId): TradingBlog {
                return TradingBlog::create($this->attributesFromApi($data) + [
                    'slug' => $this->uniqueSlug($data['title']),
                    'author_id' => $request->user()->id,
                    'source' => 'MOBILE_APP',
                    'client_request_id' => $requestId !== '' ? $requestId : null,
                    'version' => 0,
                ]);
            }, 3);
        } catch (QueryException $exception) {
            if ($requestId === '' || (string) $exception->getCode() !== '23000') {
                throw $exception;
            }
            $existing = TradingBlog::query()
                ->where('author_id', $request->user()->id)
                ->where('client_request_id', $requestId)
                ->first();
            if (! $existing) {
                throw $exception;
            }

            return $this->idempotentResult($request, $existing, $data);
        }

        Log::info('Trading blog received through mobile API.', [
            'blog_id' => $blog->id,
            'user_id' => $request->user()->id,
            'status' => $blog->status,
        ]);

        return $this->success([
            'blog' => (new TradingBlogResource($blog->load('author:id,name,username')))->resolve($request),
            'idempotent_replay' => false,
        ], $blog->status === TradingBlog::STATUS_PUBLISHED
            ? 'Trading blog published successfully.'
            : 'Trading blog draft created.', 201);
    }

    public function update(UpdateTradingBlogRequest $request, TradingBlog $blog): JsonResponse
    {
        $data = $request->validated();
        if (($data['status'] ?? null) === TradingBlog::STATUS_PUBLISHED) {
            Gate::authorize('publish', $blog);
        }

        $result = DB::transaction(function () use ($blog, $data): array {
            $locked = TradingBlog::query()->lockForUpdate()->findOrFail($blog->id);
            if (isset($data['expected_version']) && (int) $data['expected_version'] !== (int) $locked->version) {
                return ['conflict' => true];
            }

            $attributes = $this->attributesFromApi($data, $locked);
            if (array_key_exists('title', $data) && $data['title'] !== $locked->title) {
                $attributes['slug'] = $this->uniqueSlug($data['title'], $locked->id);
            }
            $locked->fill($attributes);
            $locked->version = (int) $locked->version + 1;
            $locked->save();

            return ['blog' => $locked];
        }, 3);

        if (isset($result['conflict'])) {
            return $this->failure('The blog was changed by another user. Refresh and retry.', [], 409);
        }

        return $this->success([
            'blog' => (new TradingBlogResource($result['blog']->fresh('author')))->resolve($request),
        ], 'Trading blog updated successfully.');
    }

    public function publish(Request $request, TradingBlog $blog): JsonResponse
    {
        Gate::authorize('publish', $blog);
        $request->validate(['expected_version' => ['nullable', 'integer', 'min:0']]);

        return $this->changePublicationStatus($request, $blog, TradingBlog::STATUS_PUBLISHED);
    }

    public function unpublish(Request $request, TradingBlog $blog): JsonResponse
    {
        Gate::authorize('publish', $blog);
        $request->validate(['expected_version' => ['nullable', 'integer', 'min:0']]);

        return $this->changePublicationStatus($request, $blog, TradingBlog::STATUS_DRAFT);
    }

    public function destroy(Request $request, TradingBlog $blog): JsonResponse
    {
        Gate::authorize('delete', $blog);
        $coverImage = $blog->cover_image;
        $blog->delete();
        $this->deleteLocalCoverImage($coverImage);

        return $this->success(null, 'Trading blog deleted successfully.');
    }

    public function uploadImage(Request $request): JsonResponse
    {
        Gate::authorize('create', TradingBlog::class);
        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
        ]);
        $image = $data['image'];
        $fileName = 'trading_blog_'.Str::uuid().'.'.$image->getClientOriginalExtension();
        $destination = public_path('upload/trading_blogs');
        if (! File::exists($destination)) {
            File::makeDirectory($destination, 0755, true);
        }
        $image->move($destination, $fileName);
        $path = 'upload/trading_blogs/'.$fileName;

        return $this->success([
            'path' => $path,
            'url' => asset($path),
        ], 'Trading blog image uploaded.', 201);
    }

    public function stream(Request $request): StreamedResponse
    {
        abort_unless(config('trading_blogs.realtime_enabled'), 503, 'Realtime blog updates are disabled.');

        return response()->stream(function (): void {
            $lastVersion = '';
            echo "retry: 3000\n";
            echo "event: ready\n";
            echo 'data: '.json_encode(['event' => 'ready', 'server_time' => now()->toIso8601String()])."\n\n";
            @ob_flush();
            flush();

            for ($second = 0; $second < 25 && ! connection_aborted(); $second++) {
                $snapshot = TradingBlog::query()
                    ->published()
                    ->selectRaw('COUNT(*) as aggregate_count, MAX(updated_at) as latest_update, MAX(id) as latest_id')
                    ->first();
                $version = implode(':', [
                    $snapshot?->aggregate_count ?? 0,
                    $snapshot?->latest_update ?? 'none',
                    $snapshot?->latest_id ?? 0,
                ]);
                if ($lastVersion !== '' && $version !== $lastVersion) {
                    echo "event: trading.blog.changed\n";
                    echo 'data: '.json_encode([
                        'event' => 'trading.blog.changed',
                        'changed_at' => now()->toIso8601String(),
                    ])."\n\n";
                } elseif ($second > 0 && $second % 10 === 0) {
                    echo ': keep-alive '.now()->timestamp."\n\n";
                }
                $lastVersion = $version;
                @ob_flush();
                flush();
                sleep(1);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function changePublicationStatus(Request $request, TradingBlog $blog, string $status): JsonResponse
    {
        $result = DB::transaction(function () use ($request, $blog, $status): array {
            $locked = TradingBlog::query()->lockForUpdate()->findOrFail($blog->id);
            if ($request->filled('expected_version')
                && $request->integer('expected_version') !== (int) $locked->version) {
                return ['conflict' => true];
            }
            if ($locked->status === $status) {
                return ['blog' => $locked, 'changed' => false];
            }
            $locked->forceFill([
                'status' => $status,
                'published_at' => $status === TradingBlog::STATUS_PUBLISHED ? now() : null,
                'is_featured' => $status === TradingBlog::STATUS_PUBLISHED ? $locked->is_featured : false,
                'version' => (int) $locked->version + 1,
            ])->save();

            return ['blog' => $locked, 'changed' => true];
        }, 3);

        if (isset($result['conflict'])) {
            return $this->failure('The blog was changed by another user. Refresh and retry.', [], 409);
        }

        $verb = $status === TradingBlog::STATUS_PUBLISHED ? 'published' : 'unpublished';

        return $this->success([
            'blog' => (new TradingBlogResource($result['blog']->fresh('author')))->resolve($request),
        ], $result['changed'] ? "Trading blog {$verb} successfully." : "Trading blog is already {$verb}.");
    }

    private function attributesFromApi(array $data, ?TradingBlog $existing = null): array
    {
        $attributes = [];
        foreach ([
            'title', 'category', 'excerpt', 'content', 'is_featured',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $attributes[$field] = $data[$field];
            }
        }
        if (array_key_exists('cover_image_url', $data)) {
            $attributes['cover_image'] = $data['cover_image_url'];
        }
        if (array_key_exists('tags', $data)) {
            $attributes['tags'] = collect($data['tags'] ?? [])->map(fn ($tag) => trim((string) $tag))->filter()->implode(', ');
        }

        $status = $data['status'] ?? $existing?->status ?? TradingBlog::STATUS_PUBLISHED;
        $attributes['status'] = $status;
        if ($status === TradingBlog::STATUS_PUBLISHED) {
            $attributes['published_at'] = $data['published_at'] ?? $existing?->published_at ?? now();
        } else {
            $attributes['published_at'] = null;
            $attributes['is_featured'] = false;
        }
        if (blank($attributes['excerpt'] ?? $existing?->excerpt)) {
            $attributes['excerpt'] = Str::words(strip_tags((string) ($attributes['content'] ?? $existing?->content)), 30);
        }

        return $attributes;
    }

    private function sameSubmission(TradingBlog $blog, array $data): bool
    {
        $incoming = $this->attributesFromApi($data, $blog);
        foreach ($incoming as $field => $value) {
            if ($field === 'published_at') {
                continue;
            }
            $stored = $blog->{$field};
            if (is_array($value)) {
                if (json_encode($stored ?? []) !== json_encode($value)) {
                    return false;
                }
            } elseif ((string) $stored !== (string) $value) {
                return false;
            }
        }

        return true;
    }

    private function idempotentResult(Request $request, TradingBlog $blog, array $data): JsonResponse
    {
        if (! $this->sameSubmission($blog, $data)) {
            return $this->failure('This idempotency key was already used for a different trading blog.', [], 409);
        }

        return $this->success([
            'blog' => (new TradingBlogResource($blog->load('author:id,name,username')))->resolve($request),
            'idempotent_replay' => true,
        ], 'Trading blog submission already received.');
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'trading-post';
        $slug = $base;
        $suffix = 2;
        while (TradingBlog::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function deleteLocalCoverImage(?string $path): void
    {
        if (filled($path) && ! Str::startsWith($path, ['http://', 'https://']) && File::exists(public_path($path))) {
            File::delete(public_path($path));
        }
    }
}
