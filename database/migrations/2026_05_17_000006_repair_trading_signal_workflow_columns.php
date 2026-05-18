<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->repairSignalTable('trading_signals');
        $this->repairSignalTable('trading_signals_backup');

        foreach (['trading_signals', 'trading_signals_backup'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (Schema::hasColumn($table, 'IsDone')) {
                DB::table($table)
                    ->where('status', 14)
                    ->update(['IsDone' => 1]);
            }

            if (Schema::hasColumn($table, 'is_done')) {
                DB::table($table)
                    ->where('status', 14)
                    ->update(['is_done' => 1]);
            }
        }
    }

    public function down(): void
    {
        $dropColumns = [
            'trading_signals' => [
                'cancel_reason',
                'IsSetBE',
                'IsBE',
                'IsDone',
                'is_done',
                'category',
                'signal_image',
                'community_category',
                'community_target',
            ],
            'trading_signals_backup' => [
                'cancel_reason',
                'IsDone',
                'category',
            ],
        ];

        foreach ($dropColumns as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    Schema::table($table, function (Blueprint $tableBlueprint) use ($column) {
                        $tableBlueprint->dropColumn($column);
                    });
                }
            }
        }
    }

    private function repairSignalTable(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table) {
            if (! Schema::hasColumn($table, 'community_target')) {
                $blueprint->string('community_target')->nullable()->after('risk_level');
            }

            if (! Schema::hasColumn($table, 'community_category')) {
                $blueprint->string('community_category')->nullable()->after('community_target');
            }

            if (! Schema::hasColumn($table, 'signal_image')) {
                $blueprint->string('signal_image')->nullable()->after('community_category');
            }

            if (! Schema::hasColumn($table, 'category')) {
                $blueprint->string('category')->nullable()->after('community_category');
            }

            if (! Schema::hasColumn($table, 'is_done')) {
                $blueprint->tinyInteger('is_done')->default(0)->after('status');
            }

            if (! Schema::hasColumn($table, 'IsDone')) {
                $blueprint->tinyInteger('IsDone')->default(0)->after('is_done');
            }

            if (! Schema::hasColumn($table, 'IsBE')) {
                $blueprint->tinyInteger('IsBE')->default(0)->after('IsDone');
            }

            if (! Schema::hasColumn($table, 'IsSetBE')) {
                $blueprint->tinyInteger('IsSetBE')->default(0)->after('IsBE');
            }

            if (! Schema::hasColumn($table, 'cancel_reason')) {
                $blueprint->text('cancel_reason')->nullable()->after('IsSetBE');
            }
        });
    }
};
