<?php

namespace App\Services;

use App\Models\AppNotification;
use Illuminate\Support\Facades\Schema;

class AppNotificationService
{
    public static function notifyRoles(array $roleIds, string $title, string $message, ?string $actionUrl = null, string $type = 'general', ?int $createdBy = null): ?AppNotification
    {
        if (! self::notificationsTableReady()) {
            return null;
        }

        $roles = collect($roleIds)
            ->map(fn ($roleId): int => (int) $roleId)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return AppNotification::create([
            'created_by' => $createdBy ?? auth()->id(),
            'target_roles' => $roles,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'action_url' => $actionUrl,
            'published_at' => now(),
        ]);
    }

    public static function notifyUser(int $userId, string $title, string $message, ?string $actionUrl = null, string $type = 'general', ?int $createdBy = null): ?AppNotification
    {
        if (! self::notificationsTableReady()) {
            return null;
        }

        return AppNotification::create([
            'created_by' => $createdBy ?? auth()->id(),
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'action_url' => $actionUrl,
            'published_at' => now(),
        ]);
    }

    public static function notifyAdmins(string $title, string $message, ?string $actionUrl = null, string $type = 'general', ?int $createdBy = null): ?AppNotification
    {
        return self::notifyRoles([1, 2], $title, $message, $actionUrl, $type, $createdBy);
    }

    private static function notificationsTableReady(): bool
    {
        try {
            return Schema::hasTable('app_notifications');
        } catch (\Throwable $exception) {
            return false;
        }
    }
}
