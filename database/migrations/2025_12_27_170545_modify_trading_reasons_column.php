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
            // Change trading_reasons to JSON (nullable)
            $table->json('trading_reasons')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trading_signals', function (Blueprint $table) {
            // Rollback to TEXT (or string if you prefer)
            $table->text('trading_reasons')->nullable()->change();
        });
    }
};
