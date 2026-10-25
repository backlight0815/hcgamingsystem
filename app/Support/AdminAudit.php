<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminAudit
{
    public static function record(?User $actor, string $action, ?string $targetType = null, ?int $targetId = null, array $details = []): void
    {
        if (! $actor || ! Schema::hasTable('admin_audit_logs')) {
            return;
        }

        DB::table('admin_audit_logs')->insert([
            'actor_id' => $actor->id,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'details' => $details ? json_encode($details) : null,
            'result' => 'success',
            'created_at' => now(),
        ]);
    }
}
