<?php

namespace App\Policies;

use App\Models\TradingSignal;
use App\Models\User;

class TradingSignalPolicy
{
    public function viewAny(User $user): bool
    {
        return (int) $user->status !== 0;
    }

    public function view(User $user, TradingSignal $signal): bool
    {
        return $this->manage($user)
            || (int) $signal->source_user_id === (int) $user->id
            || (int) $signal->user_id === (int) $user->id
            || in_array($signal->workflow_status, [
                TradingSignal::WORKFLOW_PUBLISHED,
                TradingSignal::WORKFLOW_CLOSED,
                TradingSignal::WORKFLOW_CANCELLED,
                TradingSignal::WORKFLOW_EXPIRED,
            ], true);
    }

    public function create(User $user): bool
    {
        return in_array((int) $user->role_id, config('trading_signals.roles.create', []), true);
    }

    public function update(User $user, TradingSignal $signal): bool
    {
        if ($this->manage($user)) {
            return true;
        }

        $ownsSignal = (int) $signal->source_user_id === (int) $user->id
            || (int) $signal->user_id === (int) $user->id;

        return $ownsSignal
            && in_array((int) $user->role_id, config('trading_signals.roles.create', []), true)
            && in_array($signal->workflow_status, [TradingSignal::WORKFLOW_DRAFT, TradingSignal::WORKFLOW_PENDING], true);
    }

    public function publish(User $user, ?TradingSignal $signal = null): bool
    {
        return in_array((int) $user->role_id, config('trading_signals.roles.publish', []), true);
    }

    public function cancel(User $user, TradingSignal $signal): bool
    {
        return $this->manage($user) || $this->update($user, $signal);
    }

    public function close(User $user, TradingSignal $signal): bool
    {
        return $this->manage($user)
            || ((int) $signal->user_id === (int) $user->id
                && in_array((int) $user->role_id, config('trading_signals.roles.create', []), true));
    }

    public function delete(User $user, TradingSignal $signal): bool
    {
        return $this->manage($user)
            || ($this->update($user, $signal) && $signal->workflow_status === TradingSignal::WORKFLOW_DRAFT);
    }

    public function manage(User $user): bool
    {
        return in_array((int) $user->role_id, config('trading_signals.roles.manage', []), true);
    }
}
