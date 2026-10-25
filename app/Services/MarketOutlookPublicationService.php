<?php

namespace App\Services;

use App\Models\MarketAnalysis;
use App\Models\TraderOnboardingApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class MarketOutlookPublicationService
{
    public const PUBLIC_LIMIT = 6;

    public function published(): Builder
    {
        return MarketAnalysis::query()->with('community:id,name,status')
            ->where('publication_status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereHas('community', fn (Builder $query) => $query->where('status', 1))
            ->orderByDesc('published_at')->orderByDesc('id');
    }

    public function hasHistoryAccess(?User $user): bool
    {
        if (! $user || (string) $user->status !== '1') {
            return false;
        }

        if (in_array((int) $user->role_id, [1, 2, 501], true)) {
            return true;
        }

        return TraderOnboardingApplication::where('user_id', $user->id)
            ->orderByDesc('id')->value('status') === TraderOnboardingApplication::STATUS_APPROVED;
    }

    public function visible(?User $user, ?bool $hasHistoryAccess = null): Builder
    {
        $query = $this->published();
        if (! ($hasHistoryAccess ?? $this->hasHistoryAccess($user))) {
            $query->whereIn('market_analyses.id', $this->published()
                ->limit(self::PUBLIC_LIMIT)->pluck('market_analyses.id'));
        }

        return $query;
    }

    public function findVisibleOrFail(int $id, ?User $user): MarketAnalysis
    {
        return $this->visible($user)->findOrFail($id);
    }

    public function publish(MarketAnalysis $analysis): void
    {
        DB::transaction(function () use ($analysis): void {
            $locked = MarketAnalysis::lockForUpdate()->findOrFail($analysis->id);
            if ($locked->publication_status !== 'published') {
                $locked->forceFill(['publication_status' => 'published', 'published_at' => now()])->save();
            }
        });
    }

    public function unpublish(MarketAnalysis $analysis): void
    {
        $analysis->forceFill(['publication_status' => 'draft', 'published_at' => null])->save();
    }
}
