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
            // 新增 longText 字段存储 Entry Zones 描述
            $table->longText('entry_zones_description')->nullable()->after('key_zones');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('market_analyses', function (Blueprint $table) {
            $table->dropColumn('entry_zones_description');
        });
    }
};
