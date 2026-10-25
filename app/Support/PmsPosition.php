<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PmsPosition
{
    public const OPTIONS = ['Super Administrator', 'Administrator', 'Project Manager', 'Business Analyst',
        'Team Leader', 'Developer', 'QA Engineer', 'Product Owner', 'Release Manager', 'Other'];

    public const ASSIGNABLE = ['developer' => ['Developer'], 'qa' => ['QA Engineer', 'Quality Assurance Engineer']];
    public const ASSIGNABLE_CODES = ['developer' => ['DEV'], 'qa' => ['QA'], 'manager' => ['PM']];
    private const LEGACY_MANAGER = ['Project Manager'];

    public static function options(): array
    {
        return Schema::hasTable('pms_job_positions')
            ? DB::table('pms_job_positions')->where('active', true)->orderBy('name')->pluck('name')->all()
            : config('pms.job_positions', self::OPTIONS);
    }

    public static function assignmentPositions(string $type): array
    {
        return self::ASSIGNABLE[$type] ?? [];
    }

    public static function eligible(?User $user, string $type, ?int $projectId = null): bool
    {
        if (! $user || ! PmsAccess::eligible($user)) return false;
        $hasQaRole = $type === 'qa' && $projectId && DB::table('pm_project_members')
            ->where('project_id', $projectId)->where('user_id', $user->id)
            ->whereIn('role', ['QA', 'QA Lead', 'QA Engineer'])->exists();
        if (! self::matches($user, $type) && ! $hasQaRole) return false;
        return ! $projectId || DB::table('pm_project_members')->where('project_id', $projectId)->where('user_id', $user->id)->exists();
    }

    public static function matches(User $user, string $type): bool
    {
        $profile = $user->pmsProfile;
        if (! $profile) return false;
        if ($profile->job_position_id && Schema::hasTable('pms_job_positions')) {
            $position = DB::table('pms_job_positions')->where('id', $profile->job_position_id)->first(['code', 'active']);
            return $position && $position->active && in_array($position->code, self::ASSIGNABLE_CODES[$type] ?? [], true);
        }
        return in_array($profile->job_position, self::legacyPositions($type), true);
    }

    public static function matchesMember(object $member, string $type): bool
    {
        if ($member->job_position_id) {
            return (bool) $member->position_active && in_array($member->position_code, self::ASSIGNABLE_CODES[$type] ?? [], true);
        }
        return in_array($member->job_position, self::legacyPositions($type), true);
    }

    private static function legacyPositions(string $type): array
    {
        return $type === 'manager' ? self::LEGACY_MANAGER : self::assignmentPositions($type);
    }

    public static function validateAssignment(?int $userId, string $type, ?int $projectId): void
    {
        if (! $userId) return;
        $user = User::with('pmsProfile')->find($userId);
        if (! $user || ! self::eligible($user, $type, $projectId)) {
            throw ValidationException::withMessages([$type === 'qa' ? 'qa_id' : 'developer_id'
                => 'Choose an active '.($type === 'qa' ? 'QA Engineer' : 'Developer').' assigned to this project.']);
        }
    }
}
