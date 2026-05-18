<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\KnowledgeCentreDiscord;
use App\Models\Community;

class DiscordService
{
    /**
     * Send a message to Discord with optional file (PDF/Image) + optional reply.
     *
     * @param string $message
     * @param string|null $webhook
     * @param string|null $filePath
     * @param string|null $replyToMessageId
     * @return array|null
     */
    public static function send(
        string $message,
        ?string $webhook = null,
        ?string $filePath = null,
        ?string $replyToMessageId = null
    ): ?array {
        $webhookUrl = $webhook ?? config('services.discord.webhook');

        if (!$webhookUrl) {
            Log::error("Discord webhook URL not set.");
            return null;
        }

        // If file exists → send multipart (works for PDFs or images)
        if ($filePath && file_exists($filePath)) {
            return self::sendWithFile($message, $webhookUrl, $filePath, $replyToMessageId);
        }

        // Otherwise, send as text only
        return self::sendText($message, $webhookUrl, $replyToMessageId);
    }

    /**
     * Send text-only message
     */
    private static function sendText(string $message, string $webhookUrl, ?string $replyToMessageId): ?array
    {
        $payload = [
            'content' => $message,
            'allowed_mentions' => ['parse' => ['everyone']],
        ];

        if ($replyToMessageId) {
            $payload['message_reference'] = [
                'message_id' => $replyToMessageId,
                'fail_if_not_exists' => false,
            ];
        }

        $response = Http::post($webhookUrl . '?wait=true', $payload);

        if ($response->successful()) {
            $json = $response->json();
            return [
                'channel_id' => $json['channel_id'] ?? null,
                'message_id' => $json['id'] ?? null,
            ];
        }

        Log::error("Discord sendText failed: " . $response->body());
        return null;
    }

    /**
     * Send message + file (PDF/Image)
     */
    private static function sendWithFile(
        string $message,
        string $webhookUrl,
        string $filePath,
        ?string $replyToMessageId
    ): ?array {
        try {
            $multipart = [
                [
                    'name'     => 'payload_json',
                    'contents' => json_encode([
                        'content' => $message,
                        'allowed_mentions' => ['parse' => ['everyone']],
                        'message_reference' => $replyToMessageId ? [
                            'message_id' => $replyToMessageId,
                            'fail_if_not_exists' => false,
                        ] : null,
                    ]),
                ],
                [
                    'name' => 'file',
                    'contents' => fopen($filePath, 'r'),
                    'filename' => basename($filePath),
                ],
            ];

            $response = Http::asMultipart()->post($webhookUrl . '?wait=true', $multipart);

            if ($response->successful()) {
                $json = $response->json();
                return [
                    'channel_id' => $json['channel_id'] ?? null,
                    'message_id' => $json['id'] ?? null,
                ];
            }

            Log::error("Discord sendWithFile failed: " . $response->body());
            return null;

        } catch (\Exception $e) {
            Log::error("Discord file upload error: " . $e->getMessage());
            return null;
        }
    }

      public static function sendFile(string $message, string $webhookUrl, ?string $filePath = null): ?array
    {
        if (!$webhookUrl) {
            Log::error("Discord webhook URL not set.");
            return null;
        }

        try {
            $multipart = [
                [
                    'name'     => 'payload_json',
                    'contents' => json_encode([
                        'content' => $message,
                        'allowed_mentions' => ['parse' => ['everyone']],
                    ]),
                ],
            ];

            if ($filePath && file_exists($filePath)) {
                $multipart[] = [
                    'name'     => 'file',
                    'contents' => fopen($filePath, 'r'),
                    'filename' => basename($filePath),
                ];
            }

            $response = Http::asMultipart()->post($webhookUrl . '?wait=true', $multipart);

            if ($response->successful()) {
                $json = $response->json();
                return [
                    'channel_id' => $json['channel_id'] ?? null,
                    'message_id' => $json['id'] ?? null,
                ];
            }

            Log::error("Discord sendFile failed: " . $response->body());
            return null;

        } catch (\Exception $e) {
            Log::error("Discord sendFile exception: " . $e->getMessage());
            return null;
        }
    }


    /**
     * Send KnowledgeCentre PDF + message to multiple communities
     */
    public static function sendKnowledgeCentreToDiscord($knowledge): array
    {
        $results = [];

        $communities = $knowledge->community_id
            ? collect([$knowledge->community])->filter()
            : Community::where('status', 1)->get();

        $filePath = $knowledge->file_path ? public_path($knowledge->file_path) : null;
        $message = "**{$knowledge->title}**\n" . ($knowledge->description ?? '');

        foreach ($communities as $community) {
            if (!$community->discord_webhook_knowledge) {
                Log::warning("Skipping community (no webhook): {$community->name}");
                $results[] = [
                    'community' => $community->name,
                    'status' => 'failed',
                    'reason' => 'No webhook URL',
                ];
                continue;
            }

            $resp = self::send($message, $community->discord_webhook_knowledge, $filePath);

            if ($resp) {
                KnowledgeCentreDiscord::create([
                    'knowledge_centre_id' => $knowledge->id,
                    'community_id' => $community->id,
                    'community' => $community->name,
                    'message_id' => $resp['message_id'],
                    'channel_id' => $resp['channel_id'],
                ]);

                $results[] = [
                    'community' => $community->name,
                    'status' => 'success',
                    'message_id' => $resp['message_id'],
                    'channel_id' => $resp['channel_id'],
                ];
            } else {
                Log::error("Failed to send KnowledgeCentre to Discord for community: {$community->name}");
                $results[] = [
                    'community' => $community->name,
                    'status' => 'failed',
                    'reason' => 'Discord send error',
                ];
            }
        }

        return $results;
    }

    /**
     * Send to multiple communities filtered by category (works for signals/messages)
     */
    public static function sendToCategory(
        string $message,
        string $category,
        ?string $filePath = null
    ): array {
        $results = [];
        $communities = Community::where('status', 1)
            ->when(strtolower($category) !== 'all', fn($q) => $q->where('category', $category))
            ->get();

        foreach ($communities as $community) {
            if (!$community->discord_webhook) {
                $results[] = [
                    'community' => $community->name,
                    'status' => 'failed',
                    'reason' => 'No webhook URL',
                ];
                continue;
            }

            $resp = self::send($message, $community->discord_webhook, $filePath);

            $results[] = $resp
                ? ['community' => $community->name, 'status' => 'success', 'message_id' => $resp['message_id'], 'channel_id' => $resp['channel_id']]
                : ['community' => $community->name, 'status' => 'failed', 'reason' => 'Discord send error'];
        }

        return $results;
    }
}
