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
        Schema::table('market_analyses', function (Blueprint $table) {
            $table->string('Outlook_Code')->unique()->nullable()->after('id'); 
            // You can change 'after' to whichever column you want it to appear after
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('market_analyses', function (Blueprint $table) {
            $table->dropUnique(['Outlook_Code']);
            $table->dropColumn('Outlook_Code');
        });
    }
};
