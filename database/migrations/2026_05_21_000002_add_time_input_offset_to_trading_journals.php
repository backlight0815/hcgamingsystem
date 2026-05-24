<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['trading_journals', 'trading_journals_backup'] as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'time_input_offset_minutes')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $afterColumn = Schema::hasColumn($tableName, 'time_input_timezone')
                    ? 'time_input_timezone'
                    : (Schema::hasColumn($tableName, 'type') ? 'type' : 'id');

                $table->integer('time_input_offset_minutes')->nullable()->after($afterColumn);
            });

            if (Schema::hasColumn($tableName, 'time_input_timezone')) {
                DB::table($tableName)
                    ->whereNull('time_input_offset_minutes')
                    ->where('time_input_timezone', 'mt5')
                    ->update(['time_input_offset_minutes' => 120]);
            }

            DB::table($tableName)
                ->whereNull('time_input_offset_minutes')
                ->update(['time_input_offset_minutes' => 480]);
        }
    }

    public function down(): void
    {
        foreach (['trading_journals', 'trading_journals_backup'] as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'time_input_offset_minutes')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('time_input_offset_minutes');
            });
        }
    }
};
