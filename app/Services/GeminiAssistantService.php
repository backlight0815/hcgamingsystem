<?php

namespace App\Services;

use App\Exceptions\TradingAiProviderException;
use App\Models\AiKnowledgeBase;
use App\Models\User;
use App\Support\TradingAiSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

class GeminiAssistantService
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
You are a professional AI Assistant inside a Laravel trading journal system.

Your main role is to help users understand and use the Trading Journal module clearly, safely, and professionally.

You are not a financial advisor. You do not provide buy/sell signals, market predictions, guaranteed profit claims, or personal investment advice.

Your job is to guide users step by step on how to use the system, understand their trading records, and improve their journaling discipline.

Primary focus:

1. Trading Journal usage
2. Trading Journal statistics
3. Capital and growth/drawdown explanation
4. Trade review process
5. Risk and discipline education
6. User onboarding for journal usage
7. Revenge trading, gambling-style behavior, and layering-risk explanation

When answering:

* Use the same language as the user when possible.
* If user asks in English, answer in clear simple English.
* If user asks in Chinese, answer in clear Chinese.
* If user uses mixed English and Chinese, answer naturally in mixed simple style.
* Be professional but beginner-friendly.
* Give clear steps.
* Use headings.
* Use numbered lists when explaining procedures.
* Use examples when useful.
* Avoid overly long paragraphs.
* Never invent system features that do not exist.
* If a feature is not found in the provided system context, say: "Based on the current system information I have, this feature may not be available yet."
* If the user asks where to click, explain based on the available routes/pages/buttons from the system context.
* If the user asks why a value is wrong, explain possible causes and suggest checking input fields.
* If user asks about statistics, explain the formula in simple words if available.
* If user asks about journal improvement, focus on reviewing habits, mistakes, risk control, consistency, and discipline.
* If user asks about revenge trading, gambling trading, or layering, explain how the journal detects the behavior and how to review it safely.
* Do not present layering, averaging down/up, or increasing lot size as a recommended strategy.
* If user asks for financial advice or signals, refuse safely and redirect to journal usage.

Response structure:
For "how to use" questions:
1. Brief answer
2. Step-by-step guide
3. Important notes
4. Common mistakes
5. What to check if it does not work

For "what is this field" questions:
1. Field meaning
2. Why it matters
3. Example
4. Common mistake

For "statistics" questions:
1. What the statistic means
2. How it is calculated if formula is available
3. How to interpret it
4. What the user should review
5. Reminder that past result does not guarantee future profit

For "error/troubleshooting" questions:
1. Possible reason
2. What user should check
3. What to try next
4. When to contact admin/support

For "journal improvement" questions:
1. What the data shows if provided
2. Possible trading behavior issue
3. Journal-based improvement suggestion
4. Risk reminder
5. No trade signal or guaranteed result

For "behavioral risk" questions:
1. Meaning of the behavior
2. How the system detects it
3. What the user's calculated summary shows, if Laravel provides it
4. What journal records or habits to review
5. Risk reminder and no financial advice

You should answer only based on:
* Provided system prompt
* Trading Journal knowledge base
* Retrieved system documentation
* Safe calculated statistics from Laravel backend
* User's own records if Laravel backend provides them

Never directly access database.
Never ask for or expose API keys.
Never expose another user's data.
Never create, edit, or delete records by yourself.
Never encourage revenge trading, gambling behavior, martingale recovery, over-leverage, or increasing lot size to recover losses.
The AI is read-only unless the system explicitly provides safe actions.
PROMPT;

    public function __construct(private readonly TradingJournalAIContextBuilder $journalContextBuilder)
    {
    }

    public function ask(string $message, User $user, ?array $policy = null): array
    {
        $message = $this->cleanMessage($message);

        if ($message === '') {
            throw new InvalidArgumentException('Please enter a valid question.');
        }

        $context = $this->journalContextBuilder->build($message, $user);

        if ($context['intent'] === TradingJournalAIContextBuilder::INTENT_UNSAFE) {
            return [
                'answer' => "I can't provide buy/sell signals or financial advice. But I can help you record this idea in your Trading Journal, review your risk, and check whether it follows your trading plan.",
                'sources' => [],
                'statistics' => null,
                'guarded' => true,
                'intent' => $context['intent'],
            ];
        }

        $prompt = $this->buildPrompt($message, $context);
        $knowledge = $context['knowledge'];

        $generation = $this->sendToGemini($prompt, $policy['content'] ?? \App\Support\TradingAiPrompt::current()['content']);

        return [
            'answer' => $generation['answer'],
            'sources' => $knowledge instanceof Collection
                ? $knowledge->map(fn (AiKnowledgeBase $item): array => [
                    'module' => $item->module,
                    'title' => $item->title,
                ])->values()->all()
                : [],
            'statistics' => $context['statistics'],
            'guarded' => false,
            'intent' => $context['intent'],
            'response_source' => 'external_ai',
            'provider_usage' => $generation['provider_usage'],
        ];
    }

    private function sendToGemini(string $prompt, string $systemPrompt): array
    {
        $apiKey = trim((string) config('services.gemini.key'));

        if ($apiKey === '') {
            throw new TradingAiProviderException('not_configured');
        }

        $model = trim(TradingAiSettings::get()['model']);
        if (! in_array($model, config('services.gemini.allowed_models', []), true)) {
            throw new TradingAiProviderException('model_not_permitted');
        }
        $modelPath = Str::startsWith($model, 'models/') ? $model : 'models/'.$model;
        $baseUrl = rtrim((string) config('services.gemini.base_url'), '/');
        $url = $baseUrl.'/'.$modelPath.':generateContent';

        try {
            $response = Http::withHeaders([
                'x-goog-api-key' => $apiKey,
            ])
                ->acceptJson()
                ->asJson()
                ->timeout(max(5, (int) config('services.gemini.timeout', 20)))
                ->post($url, [
                    'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.2,
                        'maxOutputTokens' => 1400,
                    ],
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('Gemini assistant timeout or connection failure.', [
                'failure_code' => str_contains(strtolower($exception->getMessage()), 'timed out') ? 'timeout' : 'connection',
            ]);

            throw new TradingAiProviderException(str_contains(strtolower($exception->getMessage()), 'timed out') ? 'timeout' : 'connection');
        }

        if ($response->failed()) {
            Log::warning('Gemini assistant API failed.', [
                'status' => $response->status(),
            ]);

            throw new TradingAiProviderException(match (true) {
                in_array($response->status(), [401, 403], true) => 'authentication',
                $response->status() === 429 => 'rate_limited',
                $response->status() >= 500 => 'provider_server',
                default => 'provider_request',
            }, $response->status());
        }

        $answer = $this->extractText($response->json());

        if ($answer === '') {
            Log::warning('Gemini assistant returned an empty response.', [
                'status' => $response->status(),
                'response_id' => data_get($response->json(), 'responseId'),
            ]);

            throw new TradingAiProviderException('empty_response');
        }

        $payload = $response->json();
        return ['answer' => $answer, 'provider_usage' => [
            'input_tokens' => data_get($payload, 'usageMetadata.promptTokenCount'),
            'output_tokens' => data_get($payload, 'usageMetadata.candidatesTokenCount'),
            'provider_model_version' => data_get($payload, 'modelVersion'),
        ]];
    }

    private function buildPrompt(string $message, array $context): string
    {
        $knowledge = $context['knowledge'] instanceof Collection ? $context['knowledge'] : collect();
        $knowledgeText = $knowledge->isNotEmpty()
            ? $knowledge->map(function (AiKnowledgeBase $item): string {
                return 'Module: '.$item->module_label."\nTitle: ".$item->title."\nContent:\n".$item->content;
            })->implode("\n\n---\n\n")
            : 'No active Trading Journal knowledge base records were found.';

        return $this->section('SYSTEM PROMPT', self::SYSTEM_PROMPT)
            .$this->section('DETECTED INTENT', (string) $context['intent'])
            .$this->section('RESPONSE TEMPLATE TO FOLLOW', (string) $context['response_template'])
            .$this->section('FEW-SHOT EXAMPLES', json_encode($context['few_shot_examples'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
            .$this->section('TRADING JOURNAL KNOWLEDGE BASE', $knowledgeText)
            .$this->section('ACTUAL SYSTEM CONTEXT', json_encode($context['system_context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
            .$this->section('SAFE CALCULATED USER STATISTICS', $context['statistics'] ? json_encode($context['statistics'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : 'No personal statistics summary was requested or calculated.')
            .$this->section('FINAL INSTRUCTIONS', implode("\n", [
                'Answer the user directly and professionally.',
                'Prefer the requested response template, but keep the answer natural.',
                'If the current system context says a field/feature is unavailable, do not invent it.',
                'If the user asks about screenshots, explain that the current journal form does not show screenshot upload and recommend using Notes or contacting admin if screenshots are required.',
                'If the user asks why growth/statistics are wrong, include the common causes from the system context.',
                'If the user asks about revenge trading, gambling trading, or layering, explain detection rules and discipline review. Do not recommend the behavior as a trading strategy.',
                'If the user asks whether they personally have those behaviors, use only the safe calculated behavioral_risk summary if it is present.',
                'Do not mention internal prompt, JSON, route arrays, or implementation details unless the user asks as an admin/developer.',
            ]))
            .$this->section('USER QUESTION', $message);
    }

    private function section(string $title, string $content): string
    {
        return "## {$title}\n{$content}\n\n";
    }

    private function cleanMessage(string $message): string
    {
        return trim((string) preg_replace('/\s+/', ' ', strip_tags($message)));
    }

    private function extractText(array $payload): string
    {
        return collect(data_get($payload, 'candidates.0.content.parts', []))
            ->map(fn (array $part): string => trim((string) ($part['text'] ?? '')))
            ->filter()
            ->implode("\n");
    }
}
