<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['trading_journals', 'trading_journals_backup'] as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'time_input_timezone')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $afterColumn = Schema::hasColumn($tableName, 'type') ? 'type' : 'id';
                $table->string('time_input_timezone', 20)->default('malaysia')->after($afterColumn);
            });
        }
    }

    public function down(): void
    {
        foreach (['trading_journals', 'trading_journals_backup'] as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'time_input_timezone')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('time_input_timezone');
            });
        }
    }
};
