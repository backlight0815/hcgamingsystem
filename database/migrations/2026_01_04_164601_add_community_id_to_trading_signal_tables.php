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
        // trading_signal table
        Schema::table('trading_signals', function (Blueprint $table) {
            $table->unsignedBigInteger('community_id')->nullable()->after('id');

            $table->foreign('community_id')
                  ->references('id')
                  ->on('communities')
                  ->onDelete('cascade');
        });

        // trading_signal_backup table
        Schema::table('trading_signals_backup', function (Blueprint $table) {
            $table->unsignedBigInteger('community_id')->nullable()->after('id');

            $table->foreign('community_id')
                  ->references('id')
                  ->on('communities')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        // trading_signal table
        Schema::table('trading_signals', function (Blueprint $table) {
            $table->dropForeign(['community_id']);
            $table->dropColumn('community_id');
        });

        // trading_signal_backup table
        Schema::table('trading_signal_backup', function (Blueprint $table) {
            $table->dropForeign(['community_id']);
            $table->dropColumn('community_id');
        });
    }
};