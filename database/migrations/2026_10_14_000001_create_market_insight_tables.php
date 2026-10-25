<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('market_insight_instruments')) {
        Schema::create('market_insight_instruments', function (Blueprint $table): void {
            $table->id();
            $table->string('symbol', 24)->unique();
            $table->string('name', 100);
            $table->string('category', 24);
            $table->decimal('demo_base_price', 20, 8);
            $table->string('session_calendar', 24)->default('weekdays');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });
        }

        if (! Schema::hasTable('market_insight_configurations')) {
        Schema::create('market_insight_configurations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('version')->unique();
            $table->json('settings');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
        }

        if (! Schema::hasTable('market_insight_datasets')) {
        Schema::create('market_insight_datasets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('instrument_id');
            $table->string('timeframe', 3);
            $table->unsignedInteger('version');
            $table->string('scenario', 24);
            $table->unsignedInteger('seed');
            $table->dateTime('anchor_at');
            $table->string('source', 12)->default('MOCK');
            $table->timestamps();
            $table->unique(['instrument_id', 'timeframe', 'version'], 'mi_dataset_version_unique');
            $table->index(['instrument_id', 'timeframe', 'id'], 'mi_dataset_latest_index');
        });
        }

        if (! Schema::hasTable('market_insight_candles')) {
        Schema::create('market_insight_candles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('dataset_id');
            $table->string('timeframe', 3);
            $table->dateTime('open_at');
            $table->dateTime('close_at');
            $table->decimal('open', 20, 8);
            $table->decimal('high', 20, 8);
            $table->decimal('low', 20, 8);
            $table->decimal('close', 20, 8);
            $table->decimal('volume', 20, 4)->nullable();
            $table->boolean('is_complete')->default(true);
            $table->string('source', 12)->default('MOCK');
            $table->unique(['dataset_id', 'timeframe', 'open_at'], 'mi_candle_unique');
            $table->index(['dataset_id', 'timeframe', 'close_at'], 'mi_candle_read_index');
        });
        }

        if (! Schema::hasTable('market_insight_analyses')) {
        Schema::create('market_insight_analyses', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('dataset_id');
            $table->unsignedBigInteger('instrument_id');
            $table->string('timeframe', 3);
            $table->unsignedInteger('configuration_version');
            $table->string('engine_version', 24);
            $table->dateTime('candle_at');
            $table->dateTime('calculated_at');
            $table->string('signal', 20);
            $table->decimal('directional_score', 7, 3);
            $table->string('risk', 24);
            $table->json('result');
            $table->dateTime('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['dataset_id', 'configuration_version', 'engine_version'], 'mi_analysis_unique');
            $table->index(['instrument_id', 'timeframe', 'id'], 'mi_analysis_history_index');
        });
        }

        if (! Schema::hasTable('market_insight_execution_logs')) {
        Schema::create('market_insight_execution_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 24);
            $table->string('status', 20);
            $table->unsignedBigInteger('triggered_by')->nullable();
            $table->unsignedBigInteger('dataset_id')->nullable();
            $table->text('details')->nullable();
            $table->dateTime('started_at');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->index(['type', 'id']);
        });
        }

        $now = now();
        if (DB::table('market_insight_instruments')->count() === 0) {
        DB::table('market_insight_instruments')->insert([
            ['symbol' => 'XAUUSD', 'name' => 'Gold / US Dollar', 'category' => 'metal', 'demo_base_price' => 2350, 'session_calendar' => 'weekdays', 'is_enabled' => true, 'created_at' => $now, 'updated_at' => $now],
            ['symbol' => 'EURUSD', 'name' => 'Euro / US Dollar', 'category' => 'forex', 'demo_base_price' => 1.08, 'session_calendar' => 'weekdays', 'is_enabled' => true, 'created_at' => $now, 'updated_at' => $now],
            ['symbol' => 'GBPUSD', 'name' => 'British Pound / US Dollar', 'category' => 'forex', 'demo_base_price' => 1.27, 'session_calendar' => 'weekdays', 'is_enabled' => true, 'created_at' => $now, 'updated_at' => $now],
            ['symbol' => 'BTCUSD', 'name' => 'Bitcoin / US Dollar', 'category' => 'crypto', 'demo_base_price' => 65000, 'session_calendar' => 'all_days', 'is_enabled' => true, 'created_at' => $now, 'updated_at' => $now],
            ['symbol' => 'USDJPY', 'name' => 'US Dollar / Japanese Yen', 'category' => 'forex', 'demo_base_price' => 150, 'session_calendar' => 'weekdays', 'is_enabled' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
        }
        if (DB::table('market_insight_configurations')->count() === 0) {
        DB::table('market_insight_configurations')->insert([
            'version' => 1,
            'settings' => json_encode(\App\Services\MarketInsight\MarketInsightSettings::defaults(), JSON_THROW_ON_ERROR),
            'created_at' => $now, 'updated_at' => $now,
        ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('market_insight_execution_logs');
        Schema::dropIfExists('market_insight_analyses');
        Schema::dropIfExists('market_insight_candles');
        Schema::dropIfExists('market_insight_datasets');
        Schema::dropIfExists('market_insight_configurations');
        Schema::dropIfExists('market_insight_instruments');
    }
};
