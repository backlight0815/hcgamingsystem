<?php

namespace App\Services;

use App\Models\User;
use App\Support\PmsAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final class PmsProjectOwnerService
{
    private const PROHIBITED_CODES = ['PM', 'PJO', 'PROJECT_OWNER'];

    public function candidates(): Collection
    {
        return $this->candidateQuery()->orderBy('name')->get(['id', 'name', 'email', 'status', 'role_id'])
            ->filter(fn (User $user) => ($user->pmsProfile?->status === 'active'
                || (! $user->pmsProfile && $user->isBackofficeAdministrator()))
                && ! $this->prohibited($user))->values();
    }

    public function candidateQuery(): Builder
    {
        return User::query()->with(['pmsProfile', 'projectManagementIdentity'])->where('status', 1)
            ->where(function ($query) {
                $query->whereHas('pmsProfile', fn ($profile) => $profile->where('status', 'active'))
                    ->orWhere(fn ($admin) => $admin->whereIn('role_id', [User::ROLE_SUPER_ADMIN, User::ROLE_COMPANY_ADMIN])
                        ->whereDoesntHave('pmsProfile'));
            })->whereDoesntHave('projectManagementIdentity', fn ($identity) => $identity->where('status', '<>', 'active'))
            ->whereDoesntHave('pmsProfile', function ($profile): void {
                $profile->whereIn('job_position_id', DB::table('pms_job_positions')
                    ->whereIn('code', self::PROHIBITED_CODES)->select('id'))
                    ->orWhere(fn ($legacy) => $legacy->whereNull('job_position_id')
                        ->whereIn('job_position', ['Project Manager', 'Project Owner']));
            });
    }

    public function validate(?int $userId): ?User
    {
        if (! $userId) {
            return null;
        }
        $user = User::with('pmsProfile')->find($userId);
        if (! $user || (string) $user->status !== '1'
            || ! (PmsAccess::eligible($user) || PmsAccess::administrator($user))
            || $this->prohibited($user)) {
            throw ValidationException::withMessages(['owner_id' => 'Choose an active PMS user who does not hold a Project Manager or Project Owner position.']);
        }

        return $user;
    }

    public function prohibited(User $user): bool
    {
        $profile = $user->pmsProfile;
        if (! $profile) return false;
        if ($profile->job_position_id) {
            return DB::table('pms_job_positions')->where('id', $profile->job_position_id)
                ->whereIn('code', self::PROHIBITED_CODES)->exists();
        }
        return in_array($profile->job_position, ['Project Manager', 'Project Owner'], true);
    }
}
