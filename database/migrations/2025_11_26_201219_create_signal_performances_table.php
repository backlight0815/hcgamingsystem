<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signal_performances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('signal_id');     // FK to trading_signals
            $table->integer('tp_hit')->nullable();        // 1,2,3...
            $table->boolean('is_sl')->default(false);     // SL hit?
            $table->boolean('is_cancelled')->default(false);
            $table->float('profit_pips')->nullable();     // profit/loss in pips
            $table->float('profit_usd')->nullable();      // profit/loss in USD
            $table->timestamps();

            $table->foreign('signal_id')
                ->references('id')
                ->on('trading_signals')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signal_performances');
    }
};
