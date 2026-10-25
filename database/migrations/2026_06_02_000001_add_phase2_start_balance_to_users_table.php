<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'phase2_start_balance')) {
                $table->decimal('phase2_start_balance', 15, 2)
                    ->nullable()
                    ->after('prop_firm_phase');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'phase2_start_balance')) {
                $table->dropColumn('phase2_start_balance');
            }
        });
    }
};
