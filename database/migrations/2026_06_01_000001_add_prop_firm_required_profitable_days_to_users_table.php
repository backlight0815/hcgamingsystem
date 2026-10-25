<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'prop_firm_required_profitable_days')) {
                $table->unsignedTinyInteger('prop_firm_required_profitable_days')
                    ->nullable()
                    ->after('prop_firm_review_phase');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'prop_firm_required_profitable_days')) {
                $table->dropColumn('prop_firm_required_profitable_days');
            }
        });
    }
};
