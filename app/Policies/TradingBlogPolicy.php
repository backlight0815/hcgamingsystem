<?php

namespace App\Policies;

use App\Models\TradingBlog;
use App\Models\User;

class TradingBlogPolicy
{
    public function viewAny(User $user): bool
    {
        return (int) $user->status !== 0;
    }

    public function view(User $user, TradingBlog $blog): bool
    {
        return $this->manage($user)
            || (int) $blog->author_id === (int) $user->id
            || ($blog->status === TradingBlog::STATUS_PUBLISHED
                && $blog->published_at
                && $blog->published_at->lte(now()));
    }

    public function create(User $user): bool
    {
        return in_array((int) $user->role_id, config('trading_blogs.roles.create', []), true);
    }

    public function update(User $user, TradingBlog $blog): bool
    {
        return $this->manage($user)
            || ((int) $blog->author_id === (int) $user->id
                && in_array((int) $user->role_id, config('trading_blogs.roles.create', []), true));
    }

    public function publish(User $user, ?TradingBlog $blog = null): bool
    {
        return in_array((int) $user->role_id, config('trading_blogs.roles.publish', []), true);
    }

    public function delete(User $user, TradingBlog $blog): bool
    {
        return $this->manage($user)
            || ((int) $blog->author_id === (int) $user->id
                && $blog->status === TradingBlog::STATUS_DRAFT);
    }

    public function manage(User $user): bool
    {
        return in_array((int) $user->role_id, config('trading_blogs.roles.manage', []), true);
    }
}
