<?php

namespace App\Services;

use App\Models\User;
use App\Support\PmsAccess;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class PmsProjectOwnerService
{
    public function candidates(): Collection
    {
        return User::query()->with(['pmsProfile', 'projectManagementIdentity'])->where('status', 1)
            ->where(function ($query) {
                $query->whereHas('pmsProfile', fn ($profile) => $profile->where('status', 'active'))
                    ->orWhereIn('role_id', [User::ROLE_SUPER_ADMIN, User::ROLE_COMPANY_ADMIN]);
            })->whereDoesntHave('projectManagementIdentity', fn ($identity) => $identity->where('status', '<>', 'active'))
            ->orderBy('name')->get(['id', 'name', 'email', 'status', 'role_id'])
            ->filter(fn (User $user) => $user->pmsProfile?->status === 'active'
                || (! $user->pmsProfile && $user->isBackofficeAdministrator()))->values();
    }

    public function validate(?int $userId): ?User
    {
        if (! $userId) {
            return null;
        }
        $user = User::with('pmsProfile')->find($userId);
        if (! $user || (string) $user->status !== '1'
            || ! (PmsAccess::eligible($user) || PmsAccess::administrator($user))) {
            throw ValidationException::withMessages(['owner_id' => 'Choose an active PMS user as Project Owner.']);
        }

        return $user;
    }
}
