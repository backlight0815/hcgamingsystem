<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'referral_code')) {
            return;
        }

        // Older installations require a referral code for every users row. PMS keeps a
        // users row only as an actor/foreign-key bridge; it has no referral identity.
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            $column = DB::selectOne("SHOW COLUMNS FROM `users` LIKE 'referral_code'");
            if ($column && $column->Null === 'YES') {
                return;
            }
        } elseif ($driver === 'sqlite') {
            foreach (DB::select('PRAGMA table_info(users)') as $column) {
                if ($column->name === 'referral_code' && (int) $column->notnull === 0) {
                    return;
                }
            }
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('referral_code')->nullable()->change();
        });
    }

    public function down(): void
    {
        // PMS bridge rows can contain null here. Restoring NOT NULL would break them.
    }
};
