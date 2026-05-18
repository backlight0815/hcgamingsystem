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
            // Add user_id column
            $table->unsignedBigInteger('user_id')->nullable()->after('id');

            // Set foreign key constraint
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null'); // if user deleted, set user_id to null
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trading_signals', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
