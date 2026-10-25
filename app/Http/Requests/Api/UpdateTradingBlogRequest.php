<?php

namespace App\Http\Requests\Api;

use App\Models\TradingBlog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateTradingBlogRequest extends FormRequest
{
    public function authorize(): bool
    {
        $blog = $this->route('blog');

        return $blog instanceof TradingBlog && Gate::allows('update', $blog);
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'category' => ['sometimes', 'required', 'string', Rule::in(array_keys(TradingBlog::categories()))],
            'excerpt' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'content' => ['sometimes', 'required', 'string'],
            'cover_image_url' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'tags' => ['sometimes', 'nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:80'],
            'status' => ['sometimes', 'required', 'string', Rule::in(array_keys(TradingBlog::statuses()))],
            'is_featured' => ['sometimes', 'boolean'],
            'published_at' => ['sometimes', 'nullable', 'date'],
            'expected_version' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
