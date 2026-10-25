<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('market_analyses', 'trend_strength')) {
            Schema::table('market_analyses', function (Blueprint $table) {
                $table->string('trend_strength')->nullable()->after('trend_structure');
            });
        }

        if (! Schema::hasColumn('market_analyses', 'outlook_image')) {
            Schema::table('market_analyses', function (Blueprint $table) {
                $table->string('outlook_image')->nullable()->after('chart_signals');
            });
        }
    }

    public function down(): void
    {
        // Keep the columns: either one may have existed before this migration.
    }
};
