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
        Schema::table('users', function (Blueprint $table) {
            // Optional, default NULL (only traders will use it)
            $table->unsignedTinyInteger('prop_firm_phase')
                  ->nullable()
                  ->after('status')
                  ->comment('1 = Phase 1, 2 = Phase 2, null = not a trader');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('prop_firm_phase');
        });
    }
};