<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\TradingSignal;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1️⃣ Add column (nullable first)
        Schema::table('trading_signals', function (Blueprint $table) {
            if (!Schema::hasColumn('trading_signals', 'signal_code')) {
                $table->string('signal_code', 20)->nullable()->after('id');
            }
        });

        // 2️⃣ Backfill existing records with unique codes
        TradingSignal::whereNull('signal_code')
            ->orWhere('signal_code', '')
            ->chunkById(50, function ($signals) {
                foreach ($signals as $signal) {
                    do {
                        $code = strtoupper(
                            chr(rand(65, 90)) . rand(1000, 9999)
                        );
                    } while (
                        TradingSignal::where('signal_code', $code)->exists()
                    );

                    $signal->update([
                        'signal_code' => $code
                    ]);
                }
            });

        // 3️⃣ Add UNIQUE index
        Schema::table('trading_signals', function (Blueprint $table) {
            $table->unique('signal_code', 'trading_signals_signal_code_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trading_signals', function (Blueprint $table) {
            if (Schema::hasColumn('trading_signals', 'signal_code')) {
                $table->dropUnique('trading_signals_signal_code_unique');
                $table->dropColumn('signal_code');
            }
        });
    }
};
