<?php

namespace App\Services;

use App\Models\TradingSignal;
use App\Models\TradingSignalAudit;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TradingSignalAuditService
{
    public function record(
        TradingSignal $signal,
        string $action,
        ?User $user = null,
        array $metadata = [],
        ?string $requestId = null
    ): void {
        if (! Schema::hasTable('trading_signal_audits')) {
            return;
        }

        try {
            $attributes = [
                'user_id' => $user?->getKey(),
                'action' => $action,
                'source' => $signal->source,
                'metadata' => $this->safeMetadata($metadata),
            ];
            if ($requestId === null) {
                TradingSignalAudit::create($attributes + [
                    'trading_signal_id' => $signal->getKey(),
                ]);
            } else {
                TradingSignalAudit::firstOrCreate([
                    'trading_signal_id' => $signal->getKey(),
                    'request_id' => $requestId,
                ], $attributes);
            }
        } catch (\Throwable $exception) {
            Log::warning('Trading signal audit write failed.', [
                'signal_id' => $signal->getKey(),
                'action' => $action,
                'exception' => $exception::class,
            ]);
        }
    }

    private function safeMetadata(array $metadata): array
    {
        foreach (['authorization', 'token', 'webhook', 'webhook_url', 'password'] as $sensitiveKey) {
            unset($metadata[$sensitiveKey]);
        }

        return $metadata;
    }
}
