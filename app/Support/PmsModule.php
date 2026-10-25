<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PmsModule
{
    public static function publicAuthMode(): bool
    {
        return ModuleAccess::publicVisible(ModuleAccess::PMS);
    }

    public static function settings(): object
    {
        if (Schema::hasTable('pms_workspace_settings')) {
            $row = DB::table('pms_workspace_settings')->first();
            if ($row) {
                return $row;
            }
        }

        return (object) [
            'registration_policy' => 'disabled',
            'week_mode' => 'calendar',
            'weekend_days' => json_encode([0, 6]),
            'holidays' => json_encode([]),
            'health_thresholds' => json_encode(['attention_gap' => 10, 'risk_gap' => 20, 'reopen_limit' => 2]),
        ];
    }

    public static function registrationAllowed(): bool
    {
        return module_enabled('project_management') && in_array(self::settings()->registration_policy, ['approval', 'open'], true);
    }

    public static function invitationAllowed(): bool
    {
        return module_enabled('project_management');
    }
}
