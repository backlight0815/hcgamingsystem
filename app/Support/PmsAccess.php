<?php

namespace App\Support;

use App\Models\PmProject;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PmsAccess
{
    public const TIME_TRACKING_PERMISSIONS = ['pms.time_tracking.view', 'pms.time_tracking.reports.view',
        'pms.time_tracking.reports.export', 'pms.time_tracking.manage'];

    public static function timeTracking(?User $user, string $permission = 'pms.time_tracking.view'): bool
    {
        if (! in_array($permission, self::TIME_TRACKING_PERMISSIONS, true)) return false;
        if ($user?->isGlobalSuperAdmin()) return true;
        if (! self::eligible($user)) return false;
        if ($permission === 'pms.time_tracking.manage') return false;
        return DB::table('pm_project_members')->where('user_id', $user->id)
            ->where(function ($query) use ($user): void {
                $query->where('role', 'Project Manager');
                if (self::productOwner($user)) $query->orWhereNotNull('role');
            })->exists();
    }

    public static function timeTrackingProjectIds(?User $user): array
    {
        if ($user?->isGlobalSuperAdmin()) return DB::table('pm_projects')->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (! self::timeTracking($user)) return [];
        $query = DB::table('pm_project_members')->where('user_id', $user->id);
        if (! self::productOwner($user)) $query->where('role', 'Project Manager');
        return $query->pluck('project_id')->map(fn ($id) => (int) $id)->all();
    }
    public const ROLES = ['Project Administrator', 'Project Manager', 'Business Analyst', 'Development Lead', 'Developer', 'QA', 'QA Lead', 'QA Engineer', 'Release Manager', 'Viewer'];
    public const MANAGER_ASSIGNABLE_ROLES = ['Business Analyst', 'Developer', 'QA', 'QA Engineer', 'Viewer'];

    public static function assignableProjectRoles(?User $user): array
    {
        return self::administrator($user) ? self::ROLES : self::MANAGER_ASSIGNABLE_ROLES;
    }

    public static function canCreateTicket(?User $user, string $category, ?PmProject $project = null): bool
    {
        if ($category === 'EHN') $category = 'ENH';
        if (! in_array($category, ['REQ', 'ENH', 'PRD', 'QAT', 'TSK'], true)) return false;
        if ($user?->isGlobalSuperAdmin()) {
            return ! $project || ! $project->archived_at;
        }
        if (! self::eligible($user)) return false;
        if ($project && ($project->archived_at || ! self::allows($user, $project, 'edit_issue'))) return false;
        $profile = DB::table('pms_profiles')->where('user_id', $user->id)->first();
        if ($profile?->can_create_all_tickets) return true;
        if (! in_array($category, ['REQ', 'ENH', 'PRD', 'QAT'], true)) return false;

        if ($profile?->workspace_role) {
            $roles = collect([$profile->workspace_role]);
        } else {
            $memberships = DB::table('pm_project_members')->where('user_id', $user->id);
            if ($project) $memberships->where('project_id', $project->id);
            $roles = $memberships->pluck('role');
        }

        return $roles->contains(fn ($role) => in_array($role, ['Business Analyst', 'Project Manager'], true))
            || ($category === 'QAT' && $roles->contains(fn ($role) => in_array($role, ['QA', 'QA Lead', 'QA Engineer'], true)));
    }

    public static function administrator(?User $user): bool
    {
        if (! ($user?->isBackofficeAdministrator() ?? false)) return false;
        if (Schema::hasTable('pms_profiles') && DB::table('pms_profiles')->where('user_id', $user->id)
            ->where('status', '<>', 'active')->exists()) return false;
        if (Schema::hasTable('project_management_users') && DB::table('project_management_users')->where('user_id', $user->id)
            ->where('status', '<>', 'active')->exists()) return false;

        return true;
    }

    public static function member(?User $user): bool
    {
        return self::eligible($user) && DB::table('pm_project_members')->where('user_id', $user->id)->exists();
    }

    public static function workspace(?User $user): bool
    {
        return self::administrator($user) || self::eligible($user);
    }

    public static function eligible(?User $user): bool
    {
        if (! $user || (string) $user->status !== '1' || ! Schema::hasTable('pms_profiles')) {
            return false;
        }

        if (! DB::table('pms_profiles')->where('user_id', $user->id)->where('status', 'active')->exists()) {
            return false;
        }
        if (Schema::hasTable('project_management_users')
            && DB::table('project_management_users')->where('user_id', $user->id)->where('status', '<>', 'active')->exists()) {
            return false;
        }

        return true;
    }

    public static function role(?User $user, PmProject $project): ?string
    {
        if (self::administrator($user)) {
            return 'System Administrator';
        }
        if (! self::eligible($user)) {
            return null;
        }

        return DB::table('pm_project_members')->where('project_id', $project->id)->where('user_id', $user->id)->value('role');
    }

    public static function manageCycle(?User $user, PmProject $project): bool
    {
        if ($user?->isGlobalSuperAdmin()) return true;
        if (! self::eligible($user) || $project->archived_at) return false;
        $role = DB::table('pm_project_members')->where('project_id', $project->id)
            ->where('user_id', $user->id)->value('role');
        return $role === 'Project Manager' || ($role !== null
            && $user->pmsProfile && self::productOwner($user));
    }

    public static function productOwner(User $user): bool
    {
        $profile = $user->pmsProfile;
        if (! $profile) return false;
        if ($profile->job_position_id && Schema::hasTable('pms_job_positions')) {
            return DB::table('pms_job_positions')->where('id', $profile->job_position_id)
                ->where('active', true)->where(fn ($query) => $query->whereIn('code', ['PO', 'PRODUCT_OWNER'])
                    ->orWhere('name', 'Product Owner'))->exists();
        }
        return $profile->job_position === 'Product Owner';
    }

    public static function manageOwner(?User $user, PmProject $project): bool
    {
        if ($project->archived_at) return false;
        if (self::administrator($user)) return true;
        if (! self::eligible($user) || ! self::allows($user, $project, 'view')) return false;
        return self::role($user, $project) === 'Project Manager' || self::productOwner($user);
    }

    public static function allows(?User $user, PmProject $project, string $ability): bool
    {
        if (in_array($ability, ['edit_cycle', 'manage_cycle'], true)) {
            return self::manageCycle($user, $project);
        }
        $role = self::role($user, $project);
        if ($role === 'System Administrator' || $role === 'Project Administrator') {
            return true;
        }

        return match ($ability) {
            'view' => $role !== null,
            'manage' => $role === 'Project Manager',
            'manage_release' => in_array($role, ['Project Manager', 'Release Manager'], true),
            'edit_cycle', 'manage_cycle' => self::manageCycle($user, $project),
            'edit_project', 'edit_release', 'edit_ticket' => in_array($role, ['Project Manager', 'Business Analyst'], true),
            'edit_issue' => in_array($role, ['Project Manager', 'Business Analyst', 'Development Lead', 'Developer', 'QA', 'QA Lead', 'QA Engineer', 'Release Manager'], true),
            'transition' => in_array($role, ['Project Manager', 'Business Analyst', 'Development Lead', 'Developer', 'QA', 'QA Lead', 'QA Engineer', 'Release Manager'], true),
            'approve_production' => $role === 'Release Manager',
            default => false,
        };
    }
}
