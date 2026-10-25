<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class GeminiAiProvider implements AiProviderInterface
{
    public function model(): string
    {
        return trim((string) config('services.gemini.model', 'gemini-3.5-flash'));
    }

    public function generateStructured(string $system, array $input, array $schema, int $maxTokens = 4000): array
    {
        $key = trim((string) config('services.gemini.key'));
        if ($key === '') {
        throw new RuntimeException('AI generation is unavailable. Ask an administrator to configure the AI service.');
        }
        $path = Str::startsWith($this->model(), 'models/') ? $this->model() : 'models/'.$this->model();
        $url = rtrim((string) config('services.gemini.base_url'), '/').'/'.$path.':generateContent';
        try {
            $response = Http::withHeaders(['x-goog-api-key' => $key])->acceptJson()->asJson()
                ->timeout(max(5, (int) config('services.gemini.timeout', 20)))
                ->post($url, [
                    'systemInstruction' => ['parts' => [['text' => $system]]],
                    'contents' => [['role' => 'user', 'parts' => [['text' => json_encode($input, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]]]],
                    'generationConfig' => [
                        'temperature' => 0.2, 'maxOutputTokens' => $maxTokens,
                        'responseMimeType' => 'application/json', 'responseSchema' => $schema,
                    ],
                ]);
        } catch (Throwable $exception) {
            Log::warning('AI provider connection failed', ['exception' => $exception::class]);
            throw new RuntimeException('AI generation is temporarily unavailable. Your form has not changed. Please retry.', 0, $exception);
        }
        if ($response->failed()) {
            Log::warning('AI provider request failed', ['status' => $response->status()]);
            throw new RuntimeException('AI generation is temporarily unavailable. Your form has not changed. Please retry.');
        }
        try {
            $decoded = json_decode((string) data_get($response->json(), 'candidates.0.content.parts.0.text'), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($decoded)) {
            throw new RuntimeException('Expected an object.');
            }

            return $decoded;
        } catch (Throwable $exception) {
            Log::warning('AI provider returned invalid JSON', ['exception' => $exception::class]);
            throw new RuntimeException('The AI draft did not pass validation. Your form has not changed. Please retry.', 0, $exception);
        }
    }
}
