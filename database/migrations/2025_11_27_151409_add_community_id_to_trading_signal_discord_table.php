<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
 public function up()
{
    Schema::table('trading_signal_discord', function (Blueprint $table) {
        // Add community_id (nullable first so it doesn't break existing rows)
        if (!Schema::hasColumn('trading_signal_discord', 'community_id')) {
            $table->unsignedBigInteger('community_id')->nullable()->after('trading_signal_id');

            // Add foreign key
            $table->foreign('community_id')
                  ->references('id')
                  ->on('communities')
                  ->onDelete('set null');
        }
    });
}

public function down()
{
    Schema::table('trading_signal_discord', function (Blueprint $table) {
        if (Schema::hasColumn('trading_signal_discord', 'community_id')) {
            $table->dropForeign(['community_id']);
            $table->dropColumn('community_id');
        }
    });
}
};
