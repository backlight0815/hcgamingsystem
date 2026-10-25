<?php

namespace App\Services;

use App\Models\PropFirmModel;
use App\Models\PropFirmPhaseRule;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class PropFirmRuleService
{
    public const DEFAULT_MODEL_SLUG = 'fundingpips-2-step';

    public function models(): Collection
    {
        if (! Schema::hasTable('prop_firm_models')) {
            return collect();
        }

        return PropFirmModel::query()
            ->with(['phaseRules' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function defaultModel(): ?PropFirmModel
    {
        if (! Schema::hasTable('prop_firm_models')) {
            return null;
        }

        return PropFirmModel::query()
            ->where('slug', self::DEFAULT_MODEL_SLUG)
            ->first()
            ?: PropFirmModel::query()->where('is_active', true)->orderBy('sort_order')->first();
    }

    public function modelForUser(?User $user): ?PropFirmModel
    {
        if (! Schema::hasTable('prop_firm_models')) {
            return null;
        }

        if ($user && Schema::hasColumn('users', 'prop_firm_model_id') && $user->prop_firm_model_id) {
            $model = PropFirmModel::query()
                ->where('is_active', true)
                ->find($user->prop_firm_model_id);

            if ($model) {
                return $model;
            }
        }

        return $this->defaultModel();
    }

    public function phaseRuleForUser(?User $user, ?int $phase = null): ?PropFirmPhaseRule
    {
        if (! Schema::hasTable('prop_firm_phase_rules')) {
            return null;
        }

        $model = $this->modelForUser($user);

        if (! $model) {
            return null;
        }

        if ($user && $phase === null && Schema::hasColumn('users', 'prop_firm_phase_rule_id') && $user->prop_firm_phase_rule_id) {
            $selected = PropFirmPhaseRule::query()
                ->where('prop_firm_model_id', $model->id)
                ->where('is_active', true)
                ->find($user->prop_firm_phase_rule_id);

            if ($selected) {
                return $selected;
            }
        }

        $phaseNumber = $phase ?? (int) ($user->prop_firm_phase ?? 1);
        $phaseKey = $this->phaseKeyForNumber($phaseNumber);

        return PropFirmPhaseRule::query()
            ->where('prop_firm_model_id', $model->id)
            ->where('is_active', true)
            ->where(function ($query) use ($phaseNumber, $phaseKey): void {
                $query->where('phase_number', $phaseNumber)
                    ->orWhere('phase_key', $phaseKey);
            })
            ->orderBy('sort_order')
            ->first()
            ?: $model->phaseRules()->where('is_active', true)->first();
    }

    public function controllerRulesForUser(?User $user, float $startingBalance, ?int $phase = null, array $fallback = []): array
    {
        $rule = $this->phaseRuleForUser($user, $phase);

        if (! $rule) {
            $phaseNumber = $phase ?? (int) ($user->prop_firm_phase ?? 1);

            return [
                'phase' => $phaseNumber,
                'phase_label' => $phaseNumber >= 3 ? 'Master Account' : 'Phase '.$phaseNumber,
                'model_name' => 'Default',
                'starting_balance' => $startingBalance,
                'profit_target' => (float) ($fallback['profit_target'] ?? ($phaseNumber === 2 ? 5 : 10)),
                'max_daily_loss' => (float) ($fallback['max_daily_loss'] ?? 5),
                'max_total_loss' => (float) ($fallback['max_total_loss'] ?? 10),
                'max_days' => (int) ($fallback['max_days'] ?? ($phaseNumber >= 3 ? 0 : 30)),
                'min_profitable_days' => (int) ($fallback['min_profitable_days'] ?? ($phaseNumber >= 3 ? 0 : 3)),
                'daily_reset_time' => '05:00:00',
                'timezone' => 'Asia/Kuala_Lumpur',
            ];
        }

        return [
            'phase' => (int) $rule->phase_number,
            'phase_label' => $rule->display_name,
            'model_id' => (int) $rule->prop_firm_model_id,
            'model_name' => $rule->propFirmModel?->name ?? $this->modelForUser($user)?->name ?? 'Prop Firm',
            'phase_rule_id' => (int) $rule->id,
            'phase_key' => $rule->phase_key,
            'starting_balance' => $startingBalance,
            'profit_target' => (float) $rule->profit_target_percent,
            'max_daily_loss' => (float) $rule->max_daily_loss_percent,
            'max_total_loss' => (float) $rule->max_overall_loss_percent,
            'max_days' => (int) ($rule->max_trading_days ?? 0),
            'min_profitable_days' => (int) $rule->minimum_trading_days,
            'daily_reset_time' => (string) $rule->daily_reset_time,
            'timezone' => (string) $rule->timezone,
            'leverage' => $rule->leverage,
            'news_trading_rule' => $rule->news_trading_rule,
            'overnight_holding_rule' => $rule->overnight_holding_rule,
            'weekend_holding_rule' => $rule->weekend_holding_rule,
            'inactivity_days' => $rule->inactivity_days,
            'risk_per_trade_percent' => $rule->risk_per_trade_percent !== null ? (float) $rule->risk_per_trade_percent : null,
            'reward_split' => $rule->reward_split,
            'reward_cycle' => $rule->reward_cycle,
            'consistency_rule' => $rule->consistency_rule,
        ];
    }

    public function phaseKeyForNumber(int $phase): string
    {
        return match ($phase) {
            2 => 'phase2',
            3 => 'master',
            default => 'phase1',
        };
    }
}
