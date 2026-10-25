<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_insight_datasets', function (Blueprint $table): void {
            $table->string('provider', 24)->nullable()->after('source');
            $table->unsignedInteger('revision')->default(1)->after('provider');
            $table->json('source_metadata')->nullable()->after('provider');
        });
        Schema::table('market_insight_candles', function (Blueprint $table): void {
            $table->date('session_date')->nullable()->after('close_at');
            $table->dateTime('retrieved_at')->nullable()->after('source');
        });
        Schema::table('market_insight_analyses', function (Blueprint $table): void {
            $table->unsignedInteger('dataset_revision')->default(1)->after('dataset_id');
            $table->dropUnique('mi_analysis_unique');
            $table->unique(['dataset_id', 'dataset_revision', 'configuration_version', 'engine_version', 'candle_at'], 'mi_analysis_unique');
        });
        Schema::create('market_insight_quotes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('instrument_id');
            $table->string('provider', 24);
            $table->decimal('price', 20, 8);
            $table->dateTime('quote_at')->nullable();
            $table->dateTime('retrieved_at');
            $table->boolean('is_market_open')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();
            $table->unique(['instrument_id', 'provider']);
        });
        Schema::create('market_insight_provider_status', function (Blueprint $table): void {
            $table->string('provider', 24)->primary();
            $table->string('status', 32);
            $table->string('message', 255)->nullable();
            $table->dateTime('last_success_at')->nullable();
            $table->dateTime('last_failure_at')->nullable();
            $table->unsignedInteger('credits_left')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_insight_provider_status');
        Schema::dropIfExists('market_insight_quotes');
        Schema::table('market_insight_analyses', function (Blueprint $table): void {
            $table->dropUnique('mi_analysis_unique');
            $table->unique(['dataset_id', 'configuration_version', 'engine_version'], 'mi_analysis_unique');
            $table->dropColumn('dataset_revision');
        });
        Schema::table('market_insight_candles', function (Blueprint $table): void {
            $table->dropColumn(['session_date', 'retrieved_at']);
        });
        Schema::table('market_insight_datasets', function (Blueprint $table): void {
            $table->dropColumn(['provider', 'revision', 'source_metadata']);
        });
    }
};
