<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add community_id to signal_performances
        Schema::table('signal_performances', function (Blueprint $table) {
            $table->unsignedBigInteger('community_id')->nullable()->after('id');

            // Foreign key constraint
            $table->foreign('community_id')
                  ->references('id')->on('communities')
                  ->onDelete('set null'); // optional: keeps records if community deleted
        });

        // Add community_id to signal_performances_backup
        Schema::table('signal_performances_backup', function (Blueprint $table) {
            $table->unsignedBigInteger('community_id')->nullable()->after('id');

            $table->foreign('community_id')
                  ->references('id')->on('communities')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        // Drop foreign keys first
        Schema::table('signal_performances', function (Blueprint $table) {
            $table->dropForeign(['community_id']);
            $table->dropColumn('community_id');
        });

        Schema::table('signal_performances_backup', function (Blueprint $table) {
            $table->dropForeign(['community_id']);
            $table->dropColumn('community_id');
        });
    }
};
