<?php

namespace App\Support;

use App\Models\PmProject;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PmsTicketManager
{
    public static function eligible(?User $user, PmProject $project): bool
    {
        return PmsAccess::eligible($user) && DB::table('pm_project_members')
            ->where('project_id', $project->id)->where('user_id', $user->id)
            ->where('role', 'Project Manager')->exists();
    }

    public static function validate(?int $userId, PmProject $project): void
    {
        if (! $userId) return;
        if (! self::eligible(User::find($userId), $project)) {
            throw ValidationException::withMessages([
                'project_manager_id' => 'Choose an active Project Manager assigned to this project.',
            ]);
        }
    }

    public static function defaultFor(PmProject $project): ?int
    {
        $manager = $project->manager_id ? User::find($project->manager_id) : null;
        return self::eligible($manager, $project) ? $manager->id : null;
    }

    public static function candidates($projectIds)
    {
        return DB::table('pm_project_members')->join('users', 'users.id', '=', 'pm_project_members.user_id')
            ->join('pms_profiles', 'pms_profiles.user_id', '=', 'users.id')
            ->leftJoin('project_management_users', 'project_management_users.user_id', '=', 'users.id')
            ->whereIn('pm_project_members.project_id', $projectIds)->where('pm_project_members.role', 'Project Manager')
            ->where('users.status', 1)->where('pms_profiles.status', 'active')
            ->where(fn ($query) => $query->whereNull('project_management_users.id')
                ->orWhere('project_management_users.status', 'active'))
            ->orderBy('users.name')->get(['pm_project_members.project_id', 'users.id', 'users.name',
                'pms_profiles.job_position', 'pms_profiles.department']);
    }
}
