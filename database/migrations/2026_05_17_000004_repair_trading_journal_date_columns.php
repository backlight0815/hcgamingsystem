<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->repairJournalTable('trading_journals');
        $this->repairJournalTable('trading_journals_backup');
    }

    public function down(): void
    {
        foreach (['trading_journals', 'trading_journals_backup'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                foreach (['close_date', 'open_date'] as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    private function repairJournalTable(string $tableName): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            if (! Schema::hasColumn($tableName, 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->index()->after('id');
            }

            if (! Schema::hasColumn($tableName, 'type')) {
                $table->string('type')->default('trade')->after('user_id');
            }

            if (! Schema::hasColumn($tableName, 'open_date')) {
                $afterColumn = Schema::hasColumn($tableName, 'type') ? 'type' : 'id';
                $table->dateTime('open_date')->nullable()->after($afterColumn);
            }

            if (! Schema::hasColumn($tableName, 'close_date')) {
                $table->dateTime('close_date')->nullable()->after('open_date');
            }
        });

        if (Schema::hasColumn($tableName, 'trade_date')) {
            DB::table($tableName)
                ->whereNull('open_date')
                ->update(['open_date' => DB::raw('trade_date')]);

            DB::table($tableName)
                ->whereNull('close_date')
                ->update(['close_date' => DB::raw('trade_date')]);

            DB::statement("ALTER TABLE `{$tableName}` MODIFY `trade_date` DATETIME NULL");
        }
    }
};
