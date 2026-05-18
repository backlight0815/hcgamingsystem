<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // trading_signal
        Schema::table('trading_signals', function (Blueprint $table) {
            // Drop unique index (Laravel default naming)
            $table->dropUnique(['signal_code']);
        });

        // trading_signal_backup
        Schema::table('trading_signals_backup', function (Blueprint $table) {
            $table->dropUnique(['signal_code']);
        });
    }

    public function down(): void
    {
        // Restore unique constraint if rollback
        Schema::table('trading_signals', function (Blueprint $table) {
            $table->unique('signal_code');
        });

        Schema::table('trading_signals_backup', function (Blueprint $table) {
            $table->unique('signal_code');
        });
    }
};
