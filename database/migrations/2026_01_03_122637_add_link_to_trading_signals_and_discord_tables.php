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
        // Add 'link' column to trading_signals table
        if (! Schema::hasColumn('trading_signals', 'link')) {
            Schema::table('trading_signals', function (Blueprint $table) {
                $afterColumn = Schema::hasColumn('trading_signals', 'signal_image') ? 'signal_image' : 'trigger_time';
                $table->string('link')->nullable()->after($afterColumn);
            });
        }

        // Add 'link' column to trading_signal_backup table
        if (! Schema::hasColumn('trading_signals_backup', 'link')) {
            Schema::table('trading_signals_backup', function (Blueprint $table) {
                $afterColumn = Schema::hasColumn('trading_signals_backup', 'signal_image') ? 'signal_image' : 'trigger_time';
                $table->string('link')->nullable()->after($afterColumn);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('trading_signals', 'link')) {
            Schema::table('trading_signals', function (Blueprint $table) {
                $table->dropColumn('link');
            });
        }

        if (Schema::hasColumn('trading_signals_backup', 'link')) {
            Schema::table('trading_signals_backup', function (Blueprint $table) {
                $table->dropColumn('link');
            });
        }
    }
};
