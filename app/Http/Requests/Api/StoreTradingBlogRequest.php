<?php

namespace App\Http\Requests\Api;

use App\Models\TradingBlog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreTradingBlogRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! Gate::allows('create', TradingBlog::class)) {
            return false;
        }

        return $this->input('status', TradingBlog::STATUS_PUBLISHED) !== TradingBlog::STATUS_PUBLISHED
            || Gate::allows('publish', TradingBlog::class);
    }

    protected function prepareForValidation(): void
    {
        $requestId = trim((string) ($this->header('X-Idempotency-Key') ?: $this->input('client_request_id')));
        if ($requestId !== '') {
            $this->merge(['client_request_id' => $requestId]);
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(array_keys(TradingBlog::categories()))],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'content' => ['required', 'string'],
            'cover_image_url' => ['nullable', 'url:http,https', 'max:2048'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:80'],
            'status' => ['nullable', 'string', Rule::in(array_keys(TradingBlog::statuses()))],
            'is_featured' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'client_request_id' => ['nullable', 'string', 'max:120'],
        ];
    }
}
