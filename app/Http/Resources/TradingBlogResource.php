<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class TradingBlogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'title' => $this->text($this->title),
            'title_zh' => '',
            'title_i18n' => (object) [],
            'slug' => $this->text($this->slug),
            'category' => $this->text($this->category),
            'category_label' => $this->text($this->category_label),
            'excerpt' => $this->text($this->excerpt),
            'excerpt_zh' => '',
            'excerpt_i18n' => (object) [],
            'content' => $this->text($this->content),
            'content_zh' => '',
            'content_i18n' => (object) [],
            'cover_image' => $this->coverImageUrl(),
            'tags' => $this->tagsList(),
            'tags_i18n' => [],
            'status' => $this->text($this->status),
            'is_featured' => (bool) $this->is_featured,
            'published_at' => $this->published_at?->toIso8601String(),
            'views' => (int) $this->views,
            'reading_minutes' => $this->reading_minutes,
            'source' => $this->text($this->source ?: 'WEBSITE'),
            'version' => (int) ($this->version ?? 0),
            'author' => $this->whenLoaded('author', fn (): ?array => $this->author ? [
                'id' => (string) $this->author->id,
                'name' => $this->text($this->author->name),
                'username' => $this->text($this->author->username),
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function text(mixed $value): string
    {
        return $value === null ? '' : (string) $value;
    }

    private function tagsList(): array
    {
        return collect(explode(',', (string) $this->tags))
            ->map(fn (string $tag): string => trim($tag))
            ->filter()
            ->values()
            ->all();
    }

    private function coverImageUrl(): string
    {
        if (blank($this->cover_image)) {
            return '';
        }

        return Str::startsWith($this->cover_image, ['http://', 'https://'])
            ? $this->cover_image
            : asset($this->cover_image);
    }
}
