<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('communities') || ! Schema::hasColumn('communities', 'parent_id')) {
            return;
        }

        Schema::table('communities', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['parent_id']);
            }

            $table->dropColumn('parent_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('communities') || Schema::hasColumn('communities', 'parent_id')) {
            return;
        }

        Schema::table('communities', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id')->nullable()->after('id');

            if (DB::getDriverName() !== 'sqlite') {
                $table->foreign('parent_id')->references('id')->on('communities')->onDelete('set null');
            }
        });
    }
};
