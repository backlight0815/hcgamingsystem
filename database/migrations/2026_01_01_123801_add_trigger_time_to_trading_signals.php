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
        // Add trigger_time to trading_signal
        if (! Schema::hasColumn('trading_signals', 'trigger_time')) {
            Schema::table('trading_signals', function (Blueprint $table) {
                $afterColumn = Schema::hasColumn('trading_signals', 'signal_image') ? 'signal_image' : 'status';
                $table->dateTime('trigger_time')->nullable()->after($afterColumn);
            });
        }

        // Add trigger_time to trading_signal_backup
        if (! Schema::hasColumn('trading_signals_backup', 'trigger_time')) {
            Schema::table('trading_signals_backup', function (Blueprint $table) {
                $afterColumn = Schema::hasColumn('trading_signals_backup', 'signal_image') ? 'signal_image' : 'status';
                $table->dateTime('trigger_time')->nullable()->after($afterColumn);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('trading_signals', 'trigger_time')) {
            Schema::table('trading_signals', function (Blueprint $table) {
                $table->dropColumn('trigger_time');
            });
        }

        if (Schema::hasColumn('trading_signals_backup', 'trigger_time')) {
            Schema::table('trading_signals_backup', function (Blueprint $table) {
                $table->dropColumn('trigger_time');
            });
        }
    }
};
