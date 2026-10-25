<?php

return [
    'max_order_risk_percent' => 0.5,
    'max_setup_risk_percent' => 1.0,
    'max_orders_per_day' => 8,
    'daily_loss_limit_percent' => 5.0,
    'total_loss_limit_percent' => 10.0,
    'cooldown_after_loss_minutes' => 30,
    'minimum_rr' => 1.0,
    'preferred_rr' => 2.0,
    'safe_margin_percent' => 30.0,
    'dangerous_margin_percent' => 60.0,
    'max_planned_layers' => 3,
    'max_symbols_short_window' => 3,
    'max_strategies_short_window' => 2,
    'behavior_recovery_block_size' => 8,
    'behavior_recovery_reductions' => [
        'normal' => 25.0,
        'low' => 20.0,
        'mid' => 10.0,
        'high' => 5.0,
    ],
    'risk_alerts' => [
        'enabled' => true,
        'min_final_score' => 65.0,
        'score_jump_threshold' => 20.0,
        'behavior_score_jump_threshold' => 20.0,
        'tier_rank_jump_threshold' => 1,
        'minimum_confidence_score' => 50.0,
        'cooldown_minutes' => 60,
        'admin_roles' => [1, 2],
    ],

    'tiers' => [
        'normal' => [0, 24],
        'low' => [25, 44],
        'mid' => [45, 64],
        'high' => [65, 79],
        'critical' => [80, 100],
    ],
];
