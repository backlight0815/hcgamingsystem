<?php

use App\Support\PmsStatusRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (PmsStatusRegistry::TABLE_DOMAINS as $tableName => $domain) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedSmallInteger('status_code')->nullable()->index();
            });

            foreach (PmsStatusRegistry::definitions($domain) as $code => $key) {
                // Preserve the original status text, including case, for existing clients.
                DB::table($tableName)->whereRaw('LOWER(TRIM(status)) = ?', [mb_strtolower($key)])
                    ->update(['status_code' => $code]);
            }
            // Unknown and blank historical values deliberately retain a null code.
        }
    }

    public function down(): void
    {
        foreach (PmsStatusRegistry::TABLE_DOMAINS as $tableName => $domain) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn('status_code'));
        }
    }
};
