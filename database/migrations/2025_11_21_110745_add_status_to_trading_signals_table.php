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
        Schema::table('trading_signals', function (Blueprint $table) {
            // Integer status, default 0 = Pending
            $table->unsignedTinyInteger('status')->default(0)->after('discord_channel_id')
                ->comment('0=Pending, 1=Active, 2=TP1, 3=TP2, 4=TP3, 5=TP4, 6=TP5, 7=TP6, 8=TP7, 9=TP8, 10=TP9, 11=TP10, 12=Cancelled, 13=SL');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trading_signals', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
