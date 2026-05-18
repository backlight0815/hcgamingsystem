<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('market_analyses', function (Blueprint $table) {
            $table->id();
    // Link to communities table
            $table->unsignedBigInteger('community_id')->nullable();
            // Basic Info
            $table->string('title');                            // 标题，例如：黄金市场分析
            $table->string('market')->default('XAUUSD');        // 市场/品种
            $table->date('analysis_date');                      // 分析日期

            // Main Sections
            $table->longText('market_overview')->nullable();     // 一、市场概况
            $table->longText('trend_structure')->nullable();     // 二、趋势与结构
            $table->longText('key_zones')->nullable();           // 三、支撑/阻力区间 (可使用 JSON)
            $table->longText('analyst_view')->nullable();        // 四、分析师观点
            $table->longText('strategy')->nullable();            // 五、操作策略建议
            $table->longText('chart_signals')->nullable();       // 六、图表信号总结

            // Optional Technical Inputs
            $table->string('rsi_level')->nullable();             // RSI 信息
            $table->string('order_block')->nullable();           // Order Block / FVG

            // Discord Integration
            $table->boolean('discord_sent')->default(false);     // 是否已通知 Discord

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_analyses');
    }
};
