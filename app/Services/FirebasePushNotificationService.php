<?php

namespace App\Services;

use App\Models\TradingSignal;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FirebasePushNotificationService
{
    public function configured(): bool
    {
        return filled(config('trading_signals.firebase_project_id'))
            && filled(config('trading_signals.firebase_client_email'))
            && filled(config('trading_signals.firebase_private_key'));
    }

    public function sendPublishedSignal(TradingSignal $signal): void
    {
        if (! $this->configured()) {
            return;
        }

        $projectId = (string) config('trading_signals.firebase_project_id');
        $summary = strtoupper($signal->immediate_action).' '.$signal->trading_pair;
        if (filled($signal->entry_price)) {
            $summary .= ' @ '.$signal->entry_price;
        }
        $response = Http::acceptJson()
            ->withToken($this->accessToken())
            ->timeout(10)
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'message' => [
                    'topic' => (string) config('trading_signals.firebase_signal_topic'),
                    'notification' => [
                        'title' => 'New Trading Signal — '.strtoupper($signal->trading_pair),
                        'body' => $summary,
                    ],
                    'data' => [
                        'category' => 'signals',
                        'document_id' => (string) $signal->id,
                        'signal_uuid' => (string) $signal->signal_uuid,
                        'event' => 'trading.signal.published',
                    ],
                    'android' => ['priority' => 'high'],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Firebase rejected the signal notification with HTTP '.$response->status().'.');
        }
    }

    private function accessToken(): string
    {
        return Cache::remember('firebase_messaging_access_token', now()->addMinutes(50), function (): string {
            $now = time();
            $assertion = $this->encode(['alg' => 'RS256', 'typ' => 'JWT']).'.'.$this->encode([
                'iss' => (string) config('trading_signals.firebase_client_email'),
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]);

            $privateKey = str_replace('\\n', "\n", (string) config('trading_signals.firebase_private_key'));
            if (! openssl_sign($assertion, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('Unable to sign the Firebase access token request.');
            }

            $response = Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion.'.'.$this->base64Url($signature),
            ]);
            $token = $response->json('access_token');
            if (! $response->successful() || ! is_string($token) || $token === '') {
                throw new RuntimeException('Unable to obtain a Firebase messaging access token.');
            }

            return $token;
        });
    }

    private function encode(array $payload): string
    {
        return $this->base64Url((string) json_encode($payload, JSON_UNESCAPED_SLASHES));
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
