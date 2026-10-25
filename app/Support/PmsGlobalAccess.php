<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class PmsGlobalAccess
{
    public static function manageCycles(?User $user): bool
    {
        return (bool) ($user?->isGlobalSuperAdmin()) || ($user && PmsAccess::eligible($user)
            && (DB::table('pm_project_members')->where('user_id', $user->id)
                ->where('role', 'Project Manager')->exists()
                || (PmsAccess::productOwner($user) && DB::table('pm_project_members')
                    ->where('user_id', $user->id)->exists())));
    }

    public static function visibleProjectIds(User $user)
    {
        return PmsAccess::administrator($user)
            ? DB::table('pm_projects')->whereNull('deleted_at')->select('id')
            : DB::table('pm_project_members')->where('user_id', $user->id)->select('project_id');
    }
}
