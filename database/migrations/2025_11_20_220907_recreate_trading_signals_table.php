<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop old table if exists
        Schema::dropIfExists('trading_signals');

        // Create the new full table
        Schema::create('trading_signals', function (Blueprint $table) {
            $table->id();
            $table->string('trading_pair');
            $table->string('signal_title');
            $table->string('immediate_action');

            // New fields you want to include
            $table->string('entry_price')->nullable();
            $table->string('stop_loss')->nullable();

            // Target levels
            $table->string('target_1');
            $table->string('target_2');
            $table->string('target_3')->nullable();
            $table->string('target_4')->nullable();
            $table->string('target_5')->nullable();
            $table->string('target_6')->nullable();
            $table->string('target_7')->nullable();
            $table->string('target_8')->nullable();
            $table->string('target_9')->nullable();
            $table->string('target_10')->nullable();

            // Optional fields
            $table->text('disclaimer')->nullable();
            $table->string('risk_level', 50)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trading_signals');
    }
};
