<?php

namespace App\Support;

use App\Models\PmWorkItem;
use App\Models\User;

class PmsTicketAccess
{
    public static function view(?User $user, PmWorkItem $ticket): bool
    {
        if (! PmsAccess::workspace($user)) {
            return false;
        }

        return $ticket->project
            ? PmsAccess::allows($user, $ticket->project, 'view')
                || (PmsAccess::eligible($user) && (int) $ticket->project->owner_id === $user->id)
            : PmsAccess::administrator($user) || (int) $ticket->reporter_id === $user->id;
    }

    public static function comment(?User $user, PmWorkItem $ticket): bool
    {
        return self::view($user, $ticket) && ($ticket->project
            ? PmsAccess::allows($user, $ticket->project, 'edit_issue')
            : PmsAccess::administrator($user) || (int) $ticket->reporter_id === $user->id);
    }

    public static function manage(?User $user, PmWorkItem $ticket): bool
    {
        return $ticket->project
            ? PmsAccess::allows($user, $ticket->project, 'manage')
            : PmsAccess::administrator($user);
    }

    public static function delete(?User $user, PmWorkItem $ticket): bool
    {
        if (! $user || ! $ticket->project || $ticket->project->archived_at) return false;
        if ($user->isGlobalSuperAdmin() && PmsAccess::administrator($user)) return true;
        if (! PmsAccess::eligible($user)) return false;

        return PmsAccess::role($user, $ticket->project) === 'Project Manager'
            || (int) $ticket->project->owner_id === $user->id;
    }

    public static function verify(?User $user, PmWorkItem $ticket): bool
    {
        if (! self::view($user, $ticket)) {
            return false;
        }
        if (PmsAccess::administrator($user)) {
            return true;
        }
        $role = $ticket->project ? PmsAccess::role($user, $ticket->project) : null;

        return in_array($role, ['QA', 'QA Lead', 'QA Engineer'], true)
            && ($role === 'QA Lead' || (int) $ticket->qa_id === $user->id);
    }
}
