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
        if (Schema::hasColumn('communities', 'parent_id')) {
            return;
        }

        Schema::table('communities', function (Blueprint $table) {
            $afterColumn = Schema::hasColumn('communities', 'discord_webhook_knowledge')
                ? 'discord_webhook_knowledge'
                : 'discord_webhook';

            $table->unsignedBigInteger('parent_id')->nullable()->after($afterColumn);

            // Optional: add foreign key if you want
            $table->foreign('parent_id')
                  ->references('id')
                  ->on('communities')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('communities', 'parent_id')) {
            return;
        }

        Schema::table('communities', function (Blueprint $table) {
            try {
                $table->dropForeign(['parent_id']);
            } catch (\Throwable) {
                //
            }

            $table->dropColumn('parent_id');
        });
    }
};
