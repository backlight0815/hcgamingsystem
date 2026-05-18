<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up()
{
    if (Schema::hasColumn('trading_journals', 'capital')) {
        return;
    }

    Schema::table('trading_journals', function (Blueprint $table) {
        $table->decimal('capital', 10, 2)->nullable()->after('notes'); // Adjust 'after' column as needed
    });
}

public function down()
{
    if (! Schema::hasColumn('trading_journals', 'capital')) {
        return;
    }

    Schema::table('trading_journals', function (Blueprint $table) {
        $table->dropColumn('capital');
    });
}

};
