<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trading_pairs', function (Blueprint $table) {
            if (!Schema::hasColumn('trading_pairs', 'pip_factor')) {
                $table->decimal('pip_factor', 10, 6)->default(0.0001)->after('symbol');
            }

            if (!Schema::hasColumn('trading_pairs', 'pip_decimal')) {
                $table->unsignedTinyInteger('pip_decimal')->default(4)->after('pip_factor');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trading_pairs', function (Blueprint $table) {
            $table->dropColumn(['pip_factor', 'pip_decimal']);
        });
    }
};
