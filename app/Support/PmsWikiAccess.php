<?php

namespace App\Support;

use App\Models\PmWikiPage;
use App\Models\PmWikiSpace;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PmsWikiAccess
{
    public static function viewSpace(?User $user, PmWikiSpace $space): bool
    {
        return PmsAccess::workspace($user) && ($space->visibility === 'workspace'
            || PmsAccess::administrator($user) || (int) $space->owner_id === $user->id
            || DB::table('pm_wiki_space_members')->where('space_id', $space->id)->where('user_id', $user->id)->exists());
    }

    public static function editSpace(?User $user, PmWikiSpace $space): bool
    {
        return self::viewSpace($user, $space) && ($space->visibility === 'workspace'
            || PmsAccess::administrator($user) || (int) $space->owner_id === $user->id
            || DB::table('pm_wiki_space_members')->where('space_id', $space->id)->where('user_id', $user->id)->whereIn('role', ['owner', 'editor'])->exists());
    }

    public static function viewPage(?User $user, PmWikiPage $page): bool
    {
        if (! self::viewSpace($user, $page->space)) return false;
        if ($page->project_id && $page->access_scope !== 'workspace' && ! PmsAccess::administrator($user)
            && (! $page->project || ! PmsAccess::allows($user, $page->project, 'view'))) return false;

        return $page->status === 'published'
            || PmsAccess::administrator($user) || (int) $page->author_id === $user->id
            || (int) $page->space->owner_id === $user->id;
    }

    public static function editPage(?User $user, PmWikiPage $page): bool
    {
        return self::editSpace($user, $page->space) && self::viewPage($user, $page);
    }

    public static function manage(?User $user, PmWikiSpace $space): bool
    {
        return self::viewSpace($user, $space) && (PmsAccess::administrator($user) || (int) $space->owner_id === $user->id);
    }
}
