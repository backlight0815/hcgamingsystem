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
        Schema::create('trading_signal_discord', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trading_signal_id')->constrained('trading_signals')->onDelete('cascade');
            $table->string('community')->comment('Community identifier, e.g., HC, TY, NewBits');
            $table->string('message_id')->nullable()->comment('Discord message ID');
            $table->string('channel_id')->nullable()->comment('Discord channel ID');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trading_signal_discord');
    }
};
