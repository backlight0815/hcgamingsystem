@extends('admin.admin_master')
@section('admin')

<title>Prop Firm Config | HC Gaming Studio</title>

<style>
    .prop-config-shell {
        color: #0f172a;
    }

    .prop-config-panel {
        background: #ffffff;
        border: 1px solid #dfe5ec;
        border-radius: 8px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
    }

    .prop-config-header {
        align-items: center;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        gap: 16px;
        justify-content: space-between;
        padding: 18px 20px;
    }

    .prop-config-body {
        padding: 20px;
    }

    .prop-config-grid {
        display: grid;
        gap: 12px;
        grid-template-columns: repeat(4, minmax(140px, 1fr));
    }

    .prop-config-table th {
        color: #475569;
        font-size: 12px;
        letter-spacing: .03em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .prop-config-table td {
        vertical-align: top;
    }

    .prop-config-table textarea {
        min-height: 76px;
    }

    @media (max-width: 991px) {
        .prop-config-header {
            align-items: stretch;
            flex-direction: column;
        }

        .prop-config-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="page-content prop-config-shell">
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h4 class="mb-1 contrast-dark-title">Prop Firm Configuration</h4>
                <p class="text-muted mb-0">Administration rules for Zero, 1 Step, 2 Step, 2 Step Pro, and their phase gates.</p>
            </div>
            <a href="{{ route('admin.funded_traders.index') }}" class="btn btn-light border">
                <i class="mdi mdi-arrow-left me-1"></i> Review Centre
            </a>
        </div>

        @foreach(['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info'] as $key => $class)
            @if(session($key))
                <div class="alert alert-{{ $class }}">{{ session($key) }}</div>
            @endif
        @endforeach

        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        @forelse($models as $model)
            <div class="prop-config-panel mb-4">
                <div class="prop-config-header">
                    <div>
                        <h5 class="mb-1">{{ $model->name }}</h5>
                        <div class="text-muted small">{{ $model->slug }} | {{ $model->provider ?: 'Provider not set' }}</div>
                    </div>
                    <span class="badge {{ $model->is_active ? 'bg-success' : 'bg-secondary' }}">
                        {{ $model->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
                <div class="prop-config-body">
                    <form method="POST" action="{{ route('admin.funded_traders.config.models.update', $model) }}" class="mb-4">
                        @csrf
                        <div class="prop-config-grid align-items-end">
                            <div>
                                <label class="form-label" for="provider_{{ $model->id }}">Provider</label>
                                <input id="provider_{{ $model->id }}" name="provider" class="form-control" value="{{ old('provider', $model->provider) }}">
                            </div>
                            <div>
                                <label class="form-label" for="name_{{ $model->id }}">Model Name</label>
                                <input id="name_{{ $model->id }}" name="name" class="form-control" value="{{ old('name', $model->name) }}" required>
                            </div>
                            <div>
                                <label class="form-label" for="sort_order_{{ $model->id }}">Sort Order</label>
                                <input id="sort_order_{{ $model->id }}" name="sort_order" type="number" min="0" max="999" class="form-control" value="{{ old('sort_order', $model->sort_order) }}">
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <div class="form-check">
                                    <input type="checkbox" id="is_active_{{ $model->id }}" name="is_active" value="1" class="form-check-input" {{ old('is_active', $model->is_active) ? 'checked' : '' }}>
                                    <label for="is_active_{{ $model->id }}" class="form-check-label">Active</label>
                                </div>
                                <button type="submit" class="btn btn-primary ms-auto">
                                    <i class="mdi mdi-content-save-outline me-1"></i> Save Model
                                </button>
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="form-label" for="description_{{ $model->id }}">Description</label>
                            <textarea id="description_{{ $model->id }}" name="description" class="form-control" rows="2">{{ old('description', $model->description) }}</textarea>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered prop-config-table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Phase Rules</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($model->phaseRules as $phaseRule)
                                    <tr>
                                        <td>
                                            <form method="POST" action="{{ route('admin.funded_traders.config.phase_rules.update', $phaseRule) }}">
                                                @csrf
                                                <div class="row g-3">
                                                    <div class="col-xl-3 col-lg-6">
                                                <label class="form-label small" for="display_name_{{ $phaseRule->id }}">Display Name</label>
                                                <input id="display_name_{{ $phaseRule->id }}" name="display_name" class="form-control mb-2" value="{{ old('display_name', $phaseRule->display_name) }}" required>

                                                <label class="form-label small" for="phase_key_{{ $phaseRule->id }}">Phase Key</label>
                                                <input id="phase_key_{{ $phaseRule->id }}" name="phase_key" class="form-control mb-2" value="{{ old('phase_key', $phaseRule->phase_key) }}" required>

                                                <label class="form-label small" for="phase_number_{{ $phaseRule->id }}">Phase Number</label>
                                                <input id="phase_number_{{ $phaseRule->id }}" name="phase_number" type="number" min="1" max="3" class="form-control" value="{{ old('phase_number', $phaseRule->phase_number) }}" required>
                                                    </div>
                                                    <div class="col-xl-2 col-lg-6">
                                                <label class="form-label small" for="profit_target_percent_{{ $phaseRule->id }}">Profit Target %</label>
                                                <input id="profit_target_percent_{{ $phaseRule->id }}" name="profit_target_percent" type="number" min="0" max="100" step="0.01" class="form-control mb-2" value="{{ old('profit_target_percent', $phaseRule->profit_target_percent) }}" required>

                                                <label class="form-label small" for="minimum_trading_days_{{ $phaseRule->id }}">Minimum Trading Days</label>
                                                <input id="minimum_trading_days_{{ $phaseRule->id }}" name="minimum_trading_days" type="number" min="0" max="365" class="form-control" value="{{ old('minimum_trading_days', $phaseRule->minimum_trading_days) }}" required>
                                                    </div>
                                                    <div class="col-xl-2 col-lg-6">
                                                <label class="form-label small" for="max_daily_loss_percent_{{ $phaseRule->id }}">Max Daily Loss %</label>
                                                <input id="max_daily_loss_percent_{{ $phaseRule->id }}" name="max_daily_loss_percent" type="number" min="0" max="100" step="0.01" class="form-control mb-2" value="{{ old('max_daily_loss_percent', $phaseRule->max_daily_loss_percent) }}" required>

                                                <label class="form-label small" for="max_overall_loss_percent_{{ $phaseRule->id }}">Max Overall Loss %</label>
                                                <input id="max_overall_loss_percent_{{ $phaseRule->id }}" name="max_overall_loss_percent" type="number" min="0" max="100" step="0.01" class="form-control" value="{{ old('max_overall_loss_percent', $phaseRule->max_overall_loss_percent) }}" required>
                                                    </div>
                                                    <div class="col-xl-2 col-lg-6">
                                                <label class="form-label small" for="daily_reset_time_{{ $phaseRule->id }}">Daily Reset</label>
                                                <input id="daily_reset_time_{{ $phaseRule->id }}" name="daily_reset_time" type="time" class="form-control mb-2" value="{{ old('daily_reset_time', substr((string) $phaseRule->daily_reset_time, 0, 5)) }}" required>

                                                <label class="form-label small" for="timezone_{{ $phaseRule->id }}">Timezone</label>
                                                <input id="timezone_{{ $phaseRule->id }}" name="timezone" class="form-control mb-2" value="{{ old('timezone', $phaseRule->timezone ?: 'Asia/Kuala_Lumpur') }}" required>

                                                <label class="form-label small" for="max_trading_days_{{ $phaseRule->id }}">Max Trading Days</label>
                                                <input id="max_trading_days_{{ $phaseRule->id }}" name="max_trading_days" type="number" min="0" max="3650" class="form-control" value="{{ old('max_trading_days', $phaseRule->max_trading_days) }}">
                                                    </div>
                                                    <div class="col-xl-3 col-lg-6">
                                                <label class="form-label small" for="leverage_{{ $phaseRule->id }}">Leverage</label>
                                                <input id="leverage_{{ $phaseRule->id }}" name="leverage" class="form-control mb-2" value="{{ old('leverage', $phaseRule->leverage) }}">

                                                <label class="form-label small" for="news_trading_rule_{{ $phaseRule->id }}">News Rule</label>
                                                <textarea id="news_trading_rule_{{ $phaseRule->id }}" name="news_trading_rule" class="form-control mb-2">{{ old('news_trading_rule', $phaseRule->news_trading_rule) }}</textarea>

                                                <label class="form-label small" for="overnight_holding_rule_{{ $phaseRule->id }}">Overnight Holding</label>
                                                <textarea id="overnight_holding_rule_{{ $phaseRule->id }}" name="overnight_holding_rule" class="form-control mb-2">{{ old('overnight_holding_rule', $phaseRule->overnight_holding_rule) }}</textarea>

                                                <label class="form-label small" for="weekend_holding_rule_{{ $phaseRule->id }}">Weekend Holding</label>
                                                <textarea id="weekend_holding_rule_{{ $phaseRule->id }}" name="weekend_holding_rule" class="form-control">{{ old('weekend_holding_rule', $phaseRule->weekend_holding_rule) }}</textarea>
                                                    </div>
                                                    <div class="col-xl-4 col-lg-6">
                                                <label class="form-label small" for="risk_per_trade_percent_{{ $phaseRule->id }}">Risk Per Trade %</label>
                                                <input id="risk_per_trade_percent_{{ $phaseRule->id }}" name="risk_per_trade_percent" type="number" min="0" max="100" step="0.01" class="form-control mb-2" value="{{ old('risk_per_trade_percent', $phaseRule->risk_per_trade_percent) }}">

                                                <label class="form-label small" for="inactivity_days_{{ $phaseRule->id }}">Inactivity Days</label>
                                                <input id="inactivity_days_{{ $phaseRule->id }}" name="inactivity_days" type="number" min="0" max="3650" class="form-control mb-2" value="{{ old('inactivity_days', $phaseRule->inactivity_days) }}">

                                                <label class="form-label small" for="reward_split_{{ $phaseRule->id }}">Reward Split</label>
                                                <input id="reward_split_{{ $phaseRule->id }}" name="reward_split" class="form-control mb-2" value="{{ old('reward_split', $phaseRule->reward_split) }}">

                                                <label class="form-label small" for="reward_cycle_{{ $phaseRule->id }}">Reward Cycle</label>
                                                <input id="reward_cycle_{{ $phaseRule->id }}" name="reward_cycle" class="form-control mb-2" value="{{ old('reward_cycle', $phaseRule->reward_cycle) }}">

                                                <label class="form-label small" for="consistency_rule_{{ $phaseRule->id }}">Consistency Rule</label>
                                                <textarea id="consistency_rule_{{ $phaseRule->id }}" name="consistency_rule" class="form-control">{{ old('consistency_rule', $phaseRule->consistency_rule) }}</textarea>
                                                    </div>
                                                    <div class="col-xl-3 col-lg-6">
                                                <label class="form-label small" for="sort_order_rule_{{ $phaseRule->id }}">Sort</label>
                                                <input id="sort_order_rule_{{ $phaseRule->id }}" name="sort_order" type="number" min="0" max="999" class="form-control mb-3" value="{{ old('sort_order', $phaseRule->sort_order) }}">

                                                <div class="form-check">
                                                    <input type="checkbox" id="is_active_rule_{{ $phaseRule->id }}" name="is_active" value="1" class="form-check-input" {{ old('is_active', $phaseRule->is_active) ? 'checked' : '' }}>
                                                    <label for="is_active_rule_{{ $phaseRule->id }}" class="form-check-label">Active</label>
                                                </div>
                                                    </div>
                                                    <div class="col-xl-2 col-lg-6 d-flex align-items-end">
                                                <button type="submit" class="btn btn-primary btn-sm">
                                                    <i class="mdi mdi-content-save-outline me-1"></i> Save Rule
                                                </button>
                                                    </div>
                                                </div>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @empty
            <div class="prop-config-panel">
                <div class="prop-config-body text-center text-muted py-5">
                    No prop firm models have been seeded yet.
                </div>
            </div>
        @endforelse
    </div>
</div>

@endsection
