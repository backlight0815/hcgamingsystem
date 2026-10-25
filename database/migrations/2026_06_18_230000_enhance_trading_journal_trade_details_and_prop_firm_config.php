<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $journalTables = ['trading_journals', 'trading_journals_backup'];

    private array $nullableCloseColumns = [
        'trade_date',
        'close_date',
        'exit_price',
        'pips',
        'profit_loss',
        'result',
    ];

    public function up(): void
    {
        $this->enhanceTradingReasons();

        foreach ($this->journalTables as $tableName) {
            $this->enhanceJournalTable($tableName);
            $this->makeCloseFieldsNullable($tableName);
            $this->backfillJournalStatus($tableName);
        }

        $this->createPropFirmTables();
        $this->seedPropFirmDefaults();
        $this->enhanceUsers();
        $this->backfillUserPropFirmConfig();
    }

    public function down(): void
    {
        foreach ($this->journalTables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                foreach ([
                    'status',
                    'close_handling',
                    'trading_reason_id',
                    'emotion_before_entry',
                    'emotion_during_trade',
                    'emotion_after_trade',
                    'confidence_level',
                    'discipline_rating',
                    'mistake_tags',
                    'psychology_notes',
                    'prop_firm_model_id',
                    'prop_firm_phase_rule_id',
                ] as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                foreach (['prop_firm_phase_rule_id', 'prop_firm_model_id'] as $column) {
                    if (Schema::hasColumn('users', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('prop_firm_phase_rules');
        Schema::dropIfExists('prop_firm_models');
    }

    private function enhanceTradingReasons(): void
    {
        if (! Schema::hasTable('trading_reason')) {
            return;
        }

        Schema::table('trading_reason', function (Blueprint $table): void {
            if (! Schema::hasColumn('trading_reason', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('description');
            }

            if (! Schema::hasColumn('trading_reason', 'sort_order')) {
                $table->unsignedSmallInteger('sort_order')->default(0)->after('is_active');
            }
        });

        $now = now();
        foreach ([
            'Breakout',
            'Pullback',
            'Support / Resistance',
            'Trend Continuation',
            'Reversal',
            'Liquidity Sweep',
            'News Reaction',
            'Other',
        ] as $index => $reason) {
            DB::table('trading_reason')->updateOrInsert(
                ['name' => $reason],
                [
                    'description' => null,
                    'is_active' => true,
                    'sort_order' => ($index + 1) * 10,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    private function enhanceJournalTable(string $tableName): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            if (! Schema::hasColumn($tableName, 'status')) {
                $table->string('status', 20)->default('closed')->after('type')->index();
            }

            if (! Schema::hasColumn($tableName, 'close_handling')) {
                $table->string('close_handling', 30)->default('manual_close')->after('status');
            }

            if (! Schema::hasColumn($tableName, 'trading_reason_id')) {
                $table->unsignedBigInteger('trading_reason_id')->nullable()->after('pair')->index();
            }

            if (! Schema::hasColumn($tableName, 'emotion_before_entry')) {
                $table->string('emotion_before_entry', 100)->nullable()->after('notes');
            }

            if (! Schema::hasColumn($tableName, 'emotion_during_trade')) {
                $table->string('emotion_during_trade', 100)->nullable()->after('emotion_before_entry');
            }

            if (! Schema::hasColumn($tableName, 'emotion_after_trade')) {
                $table->string('emotion_after_trade', 100)->nullable()->after('emotion_during_trade');
            }

            if (! Schema::hasColumn($tableName, 'confidence_level')) {
                $table->unsignedTinyInteger('confidence_level')->nullable()->after('emotion_after_trade');
            }

            if (! Schema::hasColumn($tableName, 'discipline_rating')) {
                $table->unsignedTinyInteger('discipline_rating')->nullable()->after('confidence_level');
            }

            if (! Schema::hasColumn($tableName, 'mistake_tags')) {
                $table->json('mistake_tags')->nullable()->after('discipline_rating');
            }

            if (! Schema::hasColumn($tableName, 'psychology_notes')) {
                $table->text('psychology_notes')->nullable()->after('mistake_tags');
            }

            if (! Schema::hasColumn($tableName, 'prop_firm_model_id')) {
                $table->unsignedBigInteger('prop_firm_model_id')->nullable()->after('time_input_offset_minutes')->index();
            }

            if (! Schema::hasColumn($tableName, 'prop_firm_phase_rule_id')) {
                $table->unsignedBigInteger('prop_firm_phase_rule_id')->nullable()->after('prop_firm_model_id')->index();
            }
        });
    }

    private function makeCloseFieldsNullable(string $tableName): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $this->makeMysqlCloseFieldsNullable($tableName);

            return;
        }

        if ($driver === 'sqlite') {
            $this->makeSqliteCloseFieldsNullable($tableName);
        }
    }

    private function makeMysqlCloseFieldsNullable(string $tableName): void
    {
        $definitions = [
            'trade_date' => 'DATETIME NULL',
            'close_date' => 'DATETIME NULL',
            'exit_price' => 'DECIMAL(15,5) NULL',
            'pips' => 'DECIMAL(15,2) NULL',
            'profit_loss' => 'DECIMAL(15,2) NULL',
            'result' => 'TINYINT UNSIGNED NULL',
        ];

        foreach ($definitions as $column => $definition) {
            if (Schema::hasColumn($tableName, $column)) {
                DB::statement(sprintf(
                    'ALTER TABLE %s MODIFY %s %s',
                    $this->quoteMysqlIdentifier($tableName),
                    $this->quoteMysqlIdentifier($column),
                    $definition
                ));
            }
        }
    }

    private function makeSqliteCloseFieldsNullable(string $tableName): void
    {
        $columns = DB::select('PRAGMA table_info("'.$tableName.'")');

        if (empty($columns)) {
            return;
        }

        $oldTable = $tableName.'_old_nullable_'.substr(md5((string) microtime(true)), 0, 8);
        $quotedTable = $this->quoteSqliteIdentifier($tableName);
        $quotedOldTable = $this->quoteSqliteIdentifier($oldTable);

        DB::statement('PRAGMA foreign_keys=OFF');
        DB::statement("ALTER TABLE {$quotedTable} RENAME TO {$quotedOldTable}");

        $definitions = [];
        $columnNames = [];

        foreach ($columns as $column) {
            $name = (string) $column->name;
            $columnNames[] = $name;

            if ((int) $column->pk === 1 && $name === 'id') {
                $definitions[] = $this->quoteSqliteIdentifier($name).' INTEGER PRIMARY KEY AUTOINCREMENT';

                continue;
            }

            $type = trim((string) ($column->type ?: 'TEXT'));
            $definition = $this->quoteSqliteIdentifier($name).' '.$type;

            if ((int) $column->notnull === 1 && ! in_array($name, $this->nullableCloseColumns, true)) {
                $definition .= ' NOT NULL';
            }

            if ($column->dflt_value !== null) {
                $definition .= ' DEFAULT '.$column->dflt_value;
            }

            $definitions[] = $definition;
        }

        DB::statement("CREATE TABLE {$quotedTable} (".implode(', ', $definitions).')');

        $columnList = collect($columnNames)
            ->map(fn (string $column): string => $this->quoteSqliteIdentifier($column))
            ->implode(', ');

        DB::statement("INSERT INTO {$quotedTable} ({$columnList}) SELECT {$columnList} FROM {$quotedOldTable}");
        DB::statement("DROP TABLE {$quotedOldTable}");
        DB::statement('PRAGMA foreign_keys=ON');
    }

    private function createPropFirmTables(): void
    {
        if (! Schema::hasTable('prop_firm_models')) {
            Schema::create('prop_firm_models', function (Blueprint $table): void {
                $table->id();
                $table->string('provider')->default('FundingPips');
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('prop_firm_phase_rules')) {
            Schema::create('prop_firm_phase_rules', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('prop_firm_model_id')->index();
                $table->string('phase_key', 40);
                $table->unsignedTinyInteger('phase_number')->default(1)->index();
                $table->string('display_name');
                $table->decimal('profit_target_percent', 6, 2)->default(0);
                $table->decimal('max_daily_loss_percent', 6, 2)->default(5);
                $table->decimal('max_overall_loss_percent', 6, 2)->default(10);
                $table->unsignedSmallInteger('minimum_trading_days')->default(3);
                $table->unsignedSmallInteger('max_trading_days')->nullable();
                $table->time('daily_reset_time')->default('05:00:00');
                $table->string('timezone')->default('Asia/Kuala_Lumpur');
                $table->string('leverage')->nullable();
                $table->text('news_trading_rule')->nullable();
                $table->text('overnight_holding_rule')->nullable();
                $table->text('weekend_holding_rule')->nullable();
                $table->unsignedSmallInteger('inactivity_days')->nullable();
                $table->decimal('risk_per_trade_percent', 6, 2)->nullable();
                $table->string('reward_split')->nullable();
                $table->string('reward_cycle')->nullable();
                $table->text('consistency_rule')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['prop_firm_model_id', 'phase_key']);
            });
        }
    }

    private function seedPropFirmDefaults(): void
    {
        if (! Schema::hasTable('prop_firm_models') || ! Schema::hasTable('prop_firm_phase_rules')) {
            return;
        }

        $now = now();
        $models = [
            [
                'slug' => 'fundingpips-zero',
                'name' => 'Zero',
                'description' => 'Configurable FundingPips-style zero model template.',
                'sort_order' => 10,
                'phases' => [
                    ['phase_key' => 'phase1', 'phase_number' => 1, 'display_name' => 'Challenge', 'profit_target_percent' => 10, 'minimum_trading_days' => 3, 'max_trading_days' => 30],
                    ['phase_key' => 'master', 'phase_number' => 3, 'display_name' => 'Master Account', 'profit_target_percent' => 0, 'minimum_trading_days' => 0, 'max_trading_days' => null],
                ],
            ],
            [
                'slug' => 'fundingpips-1-step',
                'name' => '1 Step',
                'description' => 'Single challenge phase with configurable master account rules.',
                'sort_order' => 20,
                'phases' => [
                    ['phase_key' => 'phase1', 'phase_number' => 1, 'display_name' => '1 Step Challenge', 'profit_target_percent' => 10, 'minimum_trading_days' => 3, 'max_trading_days' => 30],
                    ['phase_key' => 'master', 'phase_number' => 3, 'display_name' => 'Master Account', 'profit_target_percent' => 0, 'minimum_trading_days' => 0, 'max_trading_days' => null],
                ],
            ],
            [
                'slug' => 'fundingpips-2-step',
                'name' => '2 Step',
                'description' => 'FundingPips-style 2 Step model. Phase 1 target 8%, Phase 2 target 5%, 3 minimum trading days, 5% max daily loss, and 10% max overall loss.',
                'sort_order' => 30,
                'phases' => [
                    ['phase_key' => 'phase1', 'phase_number' => 1, 'display_name' => 'Phase 1', 'profit_target_percent' => 8, 'minimum_trading_days' => 3, 'max_trading_days' => 30],
                    ['phase_key' => 'phase2', 'phase_number' => 2, 'display_name' => 'Phase 2', 'profit_target_percent' => 5, 'minimum_trading_days' => 3, 'max_trading_days' => 30],
                    ['phase_key' => 'master', 'phase_number' => 3, 'display_name' => 'Master Account', 'profit_target_percent' => 0, 'minimum_trading_days' => 0, 'max_trading_days' => null],
                ],
            ],
            [
                'slug' => 'fundingpips-2-step-pro',
                'name' => '2 Step Pro',
                'description' => 'Two-phase professional challenge template with editable phase rules.',
                'sort_order' => 40,
                'phases' => [
                    ['phase_key' => 'phase1', 'phase_number' => 1, 'display_name' => 'Pro Phase 1', 'profit_target_percent' => 10, 'minimum_trading_days' => 3, 'max_trading_days' => 30],
                    ['phase_key' => 'phase2', 'phase_number' => 2, 'display_name' => 'Pro Phase 2', 'profit_target_percent' => 5, 'minimum_trading_days' => 3, 'max_trading_days' => 30],
                    ['phase_key' => 'master', 'phase_number' => 3, 'display_name' => 'Master Account', 'profit_target_percent' => 0, 'minimum_trading_days' => 0, 'max_trading_days' => null],
                ],
            ],
        ];

        foreach ($models as $model) {
            DB::table('prop_firm_models')->updateOrInsert(
                ['slug' => $model['slug']],
                [
                    'provider' => 'FundingPips',
                    'name' => $model['name'],
                    'description' => $model['description'],
                    'is_active' => true,
                    'sort_order' => $model['sort_order'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $modelId = DB::table('prop_firm_models')->where('slug', $model['slug'])->value('id');

            foreach ($model['phases'] as $index => $phase) {
                DB::table('prop_firm_phase_rules')->updateOrInsert(
                    [
                        'prop_firm_model_id' => $modelId,
                        'phase_key' => $phase['phase_key'],
                    ],
                    [
                        'phase_number' => $phase['phase_number'],
                        'display_name' => $phase['display_name'],
                        'profit_target_percent' => $phase['profit_target_percent'],
                        'max_daily_loss_percent' => 5,
                        'max_overall_loss_percent' => 10,
                        'minimum_trading_days' => $phase['minimum_trading_days'],
                        'max_trading_days' => $phase['max_trading_days'],
                        'daily_reset_time' => '05:00:00',
                        'timezone' => 'Asia/Kuala_Lumpur',
                        'leverage' => 'Configurable by account size',
                        'news_trading_rule' => 'Configurable',
                        'overnight_holding_rule' => 'Configurable',
                        'weekend_holding_rule' => 'Configurable',
                        'inactivity_days' => null,
                        'risk_per_trade_percent' => null,
                        'reward_split' => null,
                        'reward_cycle' => 'Configurable',
                        'consistency_rule' => 'Configurable',
                        'is_active' => true,
                        'sort_order' => ($index + 1) * 10,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }
    }

    private function enhanceUsers(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'prop_firm_model_id')) {
                $table->unsignedBigInteger('prop_firm_model_id')->nullable()->after('prop_firm_phase')->index();
            }

            if (! Schema::hasColumn('users', 'prop_firm_phase_rule_id')) {
                $table->unsignedBigInteger('prop_firm_phase_rule_id')->nullable()->after('prop_firm_model_id')->index();
            }
        });
    }

    private function backfillUserPropFirmConfig(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('prop_firm_models') || ! Schema::hasTable('prop_firm_phase_rules')) {
            return;
        }

        if (! Schema::hasColumn('users', 'prop_firm_model_id') || ! Schema::hasColumn('users', 'prop_firm_phase_rule_id')) {
            return;
        }

        $defaultModelId = DB::table('prop_firm_models')->where('slug', 'fundingpips-2-step')->value('id')
            ?: DB::table('prop_firm_models')->orderBy('sort_order')->value('id');

        if (! $defaultModelId) {
            return;
        }

        DB::table('users')
            ->whereNull('prop_firm_model_id')
            ->update(['prop_firm_model_id' => $defaultModelId]);

        foreach ([1 => 'phase1', 2 => 'phase2', 3 => 'master'] as $phaseNumber => $phaseKey) {
            $phaseRuleId = DB::table('prop_firm_phase_rules')
                ->where('prop_firm_model_id', $defaultModelId)
                ->where('phase_key', $phaseKey)
                ->value('id');

            if (! $phaseRuleId) {
                continue;
            }

            DB::table('users')
                ->whereNull('prop_firm_phase_rule_id')
                ->where(function ($query) use ($phaseNumber): void {
                    $query->where('prop_firm_phase', $phaseNumber);

                    if ($phaseNumber === 1) {
                        $query->orWhereNull('prop_firm_phase');
                    }
                })
                ->update(['prop_firm_phase_rule_id' => $phaseRuleId]);
        }
    }

    private function backfillJournalStatus(string $tableName): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'status')) {
            return;
        }

        DB::table($tableName)
            ->whereNull('status')
            ->orWhere('status', '')
            ->update(['status' => 'closed']);

        if (Schema::hasColumn($tableName, 'close_date')) {
            DB::table($tableName)
                ->where(function ($query) use ($tableName): void {
                    $query->whereNull('close_date');

                    if (Schema::hasColumn($tableName, 'result')) {
                        $query->orWhereNull('result');
                    }
                })
                ->where('type', 'trade')
                ->update(['status' => 'open']);

            DB::table($tableName)
                ->whereNotNull('close_date')
                ->whereNotNull('result')
                ->where('type', 'trade')
                ->update(['status' => 'closed']);
        }

        if (Schema::hasColumn($tableName, 'close_handling')) {
            DB::table($tableName)
                ->where(function ($query): void {
                    $query->whereNull('close_handling')->orWhere('close_handling', '');
                })
                ->update(['close_handling' => 'manual_close']);
        }
    }

    private function quoteMysqlIdentifier(string $identifier): string
    {
        return chr(96).str_replace(chr(96), chr(96).chr(96), $identifier).chr(96);
    }

    private function quoteSqliteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
};
