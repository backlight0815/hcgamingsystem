<?php

namespace App\Services;

use App\Models\PmProject;
use App\Models\PmsProfile;
use App\Models\ProjectManagementUser;
use App\Models\User;
use App\Support\PmsAccess;
use App\Support\PmsModule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PmsInvitationService
{
    public function status(string $token): string
    {
        if (! PmsModule::invitationAllowed()) return 'unavailable';
        if (! preg_match('/^[a-f0-9]{64}$/', $token)) return 'invalid';
        $invite = DB::table('pms_invitations')->where('token_hash', hash('sha256', $token))->first();
        if (! $invite) return 'invalid';
        if ($invite->revoked_at) return 'revoked';
        if ($invite->used_count >= $invite->max_uses) return 'accepted';
        if (now()->greaterThanOrEqualTo($invite->expires_at)) return 'expired';
        return $this->usable($invite) ? 'pending' : 'unavailable';
    }

    public function valid(string $token): ?object
    {
        if (! PmsModule::invitationAllowed() || ! preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $invite = DB::table('pms_invitations')->where('token_hash', hash('sha256', $token))->first();

        return $invite && $this->usable($invite) ? $invite : null;
    }

    public function accept(string $token, callable $resolveUser, array $organization = []): User
    {
        return DB::transaction(function () use ($token, $resolveUser, $organization): User {
            if (! PmsModule::invitationAllowed() || ! preg_match('/^[a-f0-9]{64}$/', $token)) {
                throw ValidationException::withMessages(['invite' => 'This invitation is invalid or unavailable.']);
            }
            $invite = DB::table('pms_invitations')->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            if (! $invite || ! $this->usable($invite)) {
                throw ValidationException::withMessages(['invite' => 'This invitation has expired, was revoked, or has already been used.']);
            }
            $user = $resolveUser($invite);
            $identity = ProjectManagementUser::where('user_id', $user->id)->first();
            $email = strtolower($identity?->email ?? $user->email);
            if ($invite->email && $invite->email_restricted && strtolower($invite->email) !== $email) {
                throw ValidationException::withMessages(['email' => 'Sign in with the email address named in the invitation.']);
            }
            if (DB::table('pms_invitation_acceptances')->where('invitation_id', $invite->id)->where('user_id', $user->id)->exists()) {
                throw ValidationException::withMessages(['invite' => 'You have already accepted this invitation.']);
            }
            $project = $invite->project_id ? PmProject::find($invite->project_id) : null;
            if ($invite->project_id && (! $project || $project->archived_at)) {
                throw ValidationException::withMessages(['invite' => 'The invited project is no longer available.']);
            }
            $profile = PmsProfile::where('user_id', $user->id)->first();
            if ($profile && in_array($profile->status, ['inactive', 'rejected', 'suspended'], true)) {
                throw ValidationException::withMessages(['invite' => 'Contact an administrator to reactivate this membership.']);
            }
            $departmentId = $invite->department_id ?: ($organization['department_id'] ?? null);
            $positionId = $invite->job_position_id ?: ($organization['job_position_id'] ?? null);
            $department = $departmentId ? DB::table('pms_departments')->find($departmentId) : null;
            $position = $positionId ? DB::table('pms_job_positions')->find($positionId) : null;
            if ($department && $position) {
                \App\Http\Controllers\Pms\OrganizationSettingsController::validatePair($department->id, $position->id);
            } elseif ($departmentId || $positionId) {
                throw ValidationException::withMessages(['invite' => 'The assigned department or position is no longer available.']);
            }
            if (! $identity) {
                $identity = ProjectManagementUser::create([
                    'user_id' => $user->id, 'name' => $user->name, 'email' => $email,
                    'password' => $user->password, 'status' => 'active', 'legacy_shared_auth' => true,
                ]);
            } elseif ($identity->status !== 'active') {
                $identity->update(['status' => 'active']);
            }
            PmsProfile::updateOrCreate(['user_id' => $user->id], [
                'status' => 'active', 'approved_by' => $invite->created_by, 'approved_at' => now(),
                'deactivated_at' => null,
                'workspace_role' => $project ? ($profile?->workspace_role) : ($profile?->workspace_role ?: $invite->role),
                'department_id' => $department?->id ?? $profile?->department_id,
                'department' => $department?->name ?? $profile?->department,
                'job_position_id' => $position?->id ?? $profile?->job_position_id,
                'job_position' => $position?->name ?? $profile?->job_position,
                'job_title' => $profile?->job_title ?: ($organization['job_title'] ?? $position?->name),
            ]);
            if ($project) {
                DB::table('pm_project_members')->insertOrIgnore([
                    'project_id' => $project->id, 'user_id' => $user->id,
                    'role' => $invite->role, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            DB::table('pms_invitation_acceptances')->insert([
                'invitation_id' => $invite->id, 'user_id' => $user->id, 'created_at' => now(),
            ]);
            DB::table('pms_invitations')->where('id', $invite->id)->update([
                'used_count' => $invite->used_count + 1, 'updated_at' => now(),
            ]);
            DB::table('pms_registrations')->insert([
                'user_id' => $user->id, 'source' => 'invitation', 'status' => 'approved',
                'status_code' => \App\Support\PmsStatusRegistry::code('registration', 'approved'),
                'reviewed_by' => $invite->created_by, 'reviewed_at' => now(),
                'invitation_id' => $invite->id, 'referral_code' => $invite->referral_code,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('pm_audit_logs')->insert([
                'actor_id' => $user->id, 'action' => 'invitation.accepted',
                'subject_type' => 'invitation', 'subject_id' => $invite->id,
                'details' => json_encode(['project_id' => $invite->project_id, 'role' => $invite->role]),
                'created_at' => now(),
            ]);

            return $user;
        });
    }

    private function usable(object $invite): bool
    {
        if ($invite->revoked_at || $invite->used_count >= $invite->max_uses || now()->greaterThanOrEqualTo($invite->expires_at)) {
            return false;
        }
        if ($invite->project_id) {
            $project = PmProject::find($invite->project_id);
            $issuer = User::find($invite->created_by);
            return $project && ! $project->archived_at && PmsAccess::allows($issuer, $project, 'manage');
        }

        return PmsAccess::administrator(User::find($invite->created_by));
    }
}
