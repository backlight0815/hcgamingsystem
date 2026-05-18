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
        Schema::create('trading_signals_backup', function (Blueprint $table) {
            $table->id();
            $table->string('signal_code')->unique();
            $table->string('trading_pair');
            $table->string('immediate_action');
            $table->string('entry_price');
            $table->string('stop_loss');
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
            $table->text('disclaimer')->nullable();
            $table->string('risk_level', 50)->nullable();
            $table->string('community_target')->nullable();
            $table->string('community_category')->nullable();
            $table->string('signal_image')->nullable();
            $table->tinyInteger('status')->default(0);
            $table->tinyInteger('is_done')->default(0);
            $table->tinyInteger('IsBE')->default(0);
            $table->tinyInteger('IsSetBE')->default(0);
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trading_signals_backup');
    }
};
