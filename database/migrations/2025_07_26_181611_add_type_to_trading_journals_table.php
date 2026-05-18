<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('trading_journals', 'type')) {
            return;
        }

        Schema::table('trading_journals', function (Blueprint $table) {
            $table->string('type')->default('trade')->after('id'); // Can be 'trade' or 'deposit'
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('trading_journals', 'type')) {
            return;
        }

        Schema::table('trading_journals', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
